<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class SoundPackRepository
{
    public const KEYBOARD = 'keyboard';

    public const MOUSE = 'mouse';

    public function __construct(protected Filesystem $files) {}

    /**
     * Installed sound packs for a device, keyed by slug, with the default pack first.
     *
     * @return Collection<string, array{slug: string, name: string, description: string, path: string}>
     */
    public function all(string $device = self::KEYBOARD): Collection
    {
        $devicePath = $this->path().DIRECTORY_SEPARATOR.$device;

        if (! $this->files->isDirectory($devicePath)) {
            return collect();
        }

        $defaultPack = config($device === self::MOUSE ? 'larakey.defaults.mouse_pack' : 'larakey.defaults.pack');

        return collect($this->files->directories($devicePath))
            ->filter(fn (string $directory): bool => $this->files->exists($directory.DIRECTORY_SEPARATOR.'pack.json'))
            ->mapWithKeys(function (string $directory): array {
                $manifest = json_decode($this->files->get($directory.DIRECTORY_SEPARATOR.'pack.json'), true) ?: [];
                $slug = basename($directory);

                return [$slug => [
                    'slug' => $slug,
                    'name' => $manifest['name'] ?? $slug,
                    'description' => $manifest['description'] ?? '',
                    'path' => $directory,
                ]];
            })
            ->sortBy([
                fn (array $pack): bool => $pack['slug'] !== $defaultPack,
                fn (array $pack): string => $pack['name'],
            ]);
    }

    /**
     * @return array{slug: string, name: string, description: string, path: string}|null
     */
    public function find(string $slug, string $device = self::KEYBOARD): ?array
    {
        return $this->all($device)->get($slug);
    }

    /**
     * The directory packs are read from, which must be a real path Electron can read.
     */
    public function path(): string
    {
        $packsPath = config('larakey.packs_path');

        return $this->isInsidePhar($packsPath) ? storage_path('app/sounds') : $packsPath;
    }

    /**
     * Copy bundled packs out of a PHAR build so the Electron engine can read them.
     */
    public function publish(): void
    {
        $packsPath = config('larakey.packs_path');

        if ($this->isInsidePhar($packsPath)) {
            $this->files->copyDirectory($packsPath, $this->path());
        }
    }

    protected function isInsidePhar(string $path): bool
    {
        return str_starts_with($path, 'phar://');
    }
}
