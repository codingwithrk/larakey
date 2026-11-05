<?php

namespace App\Providers;

use App\Events\OpenSettingsRequested;
use App\Events\ToggleSoundsRequested;
use App\Services\KeySoundSettings;
use App\Services\SoundPackRepository;
use App\Support\TrayMenu;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\GlobalShortcut;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Settings;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function __construct(
        protected KeySoundSettings $settings,
        protected SoundPackRepository $packs,
        protected TrayMenu $trayMenu,
    ) {}

    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        $this->packs->publish();
        $this->settings->sync();

        MenuBar::create()
            ->icon(public_path('menuBarIconTemplate.png'))
            ->tooltip('Larakey')
            ->onlyShowContextMenu()
            ->withContextMenu($this->trayMenu->build())
            ->showDockIcon(false);

        GlobalShortcut::key(config('larakey.toggle_shortcut'))
            ->event(ToggleSoundsRequested::class)
            ->register();

        if (! Settings::get('larakey_onboarded', false)) {
            OpenSettingsRequested::dispatch();
            Settings::set('larakey_onboarded', true);
        }
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
