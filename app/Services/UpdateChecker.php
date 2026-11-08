<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Looks up the latest Larakey release on GitHub. Unsigned macOS apps can't use Electron's
 * auto-updater, so this is how every platform finds out a new version exists.
 */
class UpdateChecker
{
    /**
     * @return array{version: string, url: string}|null Null when GitHub can't be reached.
     */
    public function latestRelease(): ?array
    {
        $owner = config('nativephp.updater.providers.github.owner');
        $repo = config('nativephp.updater.providers.github.repo');

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get("https://api.github.com/repos/{$owner}/{$repo}/releases/latest");
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed() || ! is_string($response->json('tag_name'))) {
            return null;
        }

        return [
            'version' => ltrim($response->json('tag_name'), 'v'),
            'url' => $response->json('html_url'),
        ];
    }

    public function currentVersion(): string
    {
        return (string) config('nativephp.version');
    }

    /**
     * @param  array{version: string, url: string}  $release
     */
    public function isNewer(array $release): bool
    {
        return version_compare($release['version'], $this->currentVersion(), '>');
    }
}
