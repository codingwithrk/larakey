<?php

namespace App\Services;

use App\Support\Platform;
use Native\Desktop\Facades\App;

/**
 * Launch at login across platforms. Electron handles it on macOS and Windows; on Linux
 * the preference is stored with the sound settings and the Electron engine writes an XDG
 * autostart entry, since Electron's login item API doesn't support Linux.
 */
class LaunchAtLogin
{
    public function __construct(protected KeySoundSettings $settings, protected Platform $platform) {}

    public function isEnabled(): bool
    {
        if ($this->platform->isLinux()) {
            return $this->settings->all()['launch_at_login'];
        }

        return App::openAtLogin();
    }

    /**
     * Apply the preference, returning any values that must be saved with the sound settings
     * so the caller can store everything in a single write.
     *
     * @return array{launch_at_login?: bool}
     */
    public function set(bool $enabled): array
    {
        if ($this->platform->isLinux()) {
            return ['launch_at_login' => $enabled];
        }

        App::openAtLogin($enabled);

        return [];
    }
}
