<?php

namespace App\Listeners;

use App\Events\CheckForUpdatesRequested;
use Native\Desktop\Facades\AutoUpdater;

class CheckForUpdates
{
    public function handle(CheckForUpdatesRequested $event): void
    {
        AutoUpdater::checkForUpdates();
    }
}
