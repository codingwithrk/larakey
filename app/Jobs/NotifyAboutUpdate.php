<?php

namespace App\Jobs;

use App\Events\CheckForUpdatesRequested;
use App\Services\UpdateChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Native\Desktop\Facades\Notification;
use Native\Desktop\Facades\Settings;

/**
 * Tells macOS and Linux users about a new release once, when Larakey starts. Runs on the
 * queue so a slow connection never delays startup. Clicking the notification opens the same
 * dialog as "Check for Updates…".
 */
class NotifyAboutUpdate implements ShouldQueue
{
    use Queueable;

    public const NOTIFIED_KEY = 'larakey_update_notified';

    /**
     * The check is a courtesy, so a failure is never retried.
     */
    public int $tries = 1;

    public function handle(UpdateChecker $updates): void
    {
        $release = $updates->latestRelease();

        if ($release === null || ! $updates->isNewer($release) || Settings::get(self::NOTIFIED_KEY) === $release['version']) {
            return;
        }

        Notification::new()
            ->title("Larakey {$release['version']} is available")
            ->message("You have {$updates->currentVersion()}. Click to download the update.")
            ->event(CheckForUpdatesRequested::class)
            ->show();

        Settings::set(self::NOTIFIED_KEY, $release['version']);
    }
}
