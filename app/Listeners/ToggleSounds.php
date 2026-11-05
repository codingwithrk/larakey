<?php

namespace App\Listeners;

use App\Events\ToggleSoundsRequested;
use App\Services\KeySoundSettings;
use App\Support\TrayMenu;

class ToggleSounds
{
    public function __construct(protected KeySoundSettings $settings, protected TrayMenu $trayMenu) {}

    public function handle(ToggleSoundsRequested $event): void
    {
        $this->settings->toggle();
        $this->trayMenu->refresh();
    }
}
