<?php

namespace App\Support;

use App\Events\CheckForUpdatesRequested;
use App\Events\OpenSettingsRequested;
use App\Events\ToggleSoundsRequested;
use App\Services\KeySoundSettings;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Menu\Menu as NativeMenu;

class TrayMenu
{
    public function __construct(protected KeySoundSettings $settings) {}

    public function build(): NativeMenu
    {
        $shortcut = config('larakey.toggle_shortcut');

        return Menu::make(
            Menu::checkbox('Keyboard Sounds', $this->settings->isEnabled(), $shortcut)
                ->event(ToggleSoundsRequested::class),
            Menu::label('Settings…')->event(OpenSettingsRequested::class),
            Menu::separator(),
            Menu::label('Larakey v'.config('nativephp.version'))->disabled(),
            Menu::label('Check for Updates…')->event(CheckForUpdatesRequested::class),
            Menu::separator(),
            Menu::quit('Quit Larakey'),
        );
    }

    /**
     * Push a fresh menu to the tray so its checkbox reflects the current state.
     */
    public function refresh(): void
    {
        MenuBar::contextMenu($this->build());
    }
}
