<?php

namespace App\Listeners;

use App\Events\CheckForUpdatesRequested;
use App\Services\UpdateChecker;
use App\Support\Platform;
use Native\Desktop\Facades\Alert;
use Native\Desktop\Facades\AutoUpdater;
use Native\Desktop\Facades\Shell;

class CheckForUpdates
{
    public function __construct(protected UpdateChecker $updates, protected Platform $platform) {}

    public function handle(CheckForUpdatesRequested $event): void
    {
        $release = $this->updates->latestRelease();
        $current = $this->updates->currentVersion();

        if ($release === null) {
            Alert::new()
                ->type('warning')
                ->title('Larakey')
                ->detail('Check your internet connection and try again.')
                ->show('Couldn’t check for updates');

            return;
        }

        if (! $this->updates->isNewer($release)) {
            Alert::new()
                ->type('info')
                ->title('Larakey')
                ->detail("Larakey {$current} is the latest version.")
                ->show('You’re up to date');

            return;
        }

        // The Windows build isn't signed either, but Windows' auto-updater doesn't need it to be:
        // it downloads the update now and installs it when Larakey quits.
        if ($this->platform->isWindows()) {
            $choice = Alert::new()
                ->type('info')
                ->title('Larakey')
                ->detail("You have {$current}. The update downloads in the background and installs when you quit Larakey.")
                ->buttons(['Update', 'Later'])
                ->defaultId(0)
                ->cancelId(1)
                ->show("Larakey {$release['version']} is available");

            if ($choice === 0) {
                AutoUpdater::checkForUpdates();
            }

            return;
        }

        $choice = Alert::new()
            ->type('info')
            ->title('Larakey')
            ->detail("You have {$current}. Download the new version from GitHub and install it over this one. Your settings are kept.")
            ->buttons(['Download', 'Later'])
            ->defaultId(0)
            ->cancelId(1)
            ->show("Larakey {$release['version']} is available");

        if ($choice === 0) {
            Shell::openExternal($release['url']);
        }
    }
}
