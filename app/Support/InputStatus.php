<?php

namespace App\Support;

use Native\Desktop\Facades\Settings;

/**
 * How the Electron sound engine is capturing input, as it reported it to the shared
 * settings store, plus the help to show when it can't hear the keyboard or mouse.
 */
class InputStatus
{
    public const STORE_KEY = 'larakey_engine';

    /**
     * @return array{platform: string|null, session: string|null, backend: string|null, problem: string|null}
     */
    public function current(): array
    {
        $status = Settings::get(self::STORE_KEY, []);
        $status = is_array($status) ? $status : [];

        return [
            'platform' => $status['platform'] ?? null,
            'session' => $status['session'] ?? null,
            'backend' => $status['backend'] ?? null,
            'problem' => $status['problem'] ?? null,
        ];
    }

    /**
     * @return array{title: string, body: string, command: string|null}|null
     */
    public function problem(): ?array
    {
        return match ($this->current()['problem']) {
            'accessibility' => [
                'title' => 'Allow Larakey to hear your keyboard',
                'body' => 'Open System Settings → Privacy & Security → Accessibility and turn on Larakey. Sounds start a few seconds later.',
                'command' => null,
            ],
            'input-permission' => [
                'title' => 'Larakey can\'t read your keyboard and mouse',
                'body' => 'Wayland doesn\'t let apps see global input, so Larakey reads your input devices directly, which needs your account to be in the "input" group. Run this command, then log out and back in. Note that any app you run can then read input devices.',
                'command' => 'sudo usermod -aG input $USER',
            ],
            'no-devices' => [
                'title' => 'No keyboard or mouse found',
                'body' => 'Larakey couldn\'t find any input devices. Plug in a keyboard or mouse and it will pick them up automatically.',
                'command' => null,
            ],
            'input-unavailable', 'input-crashed' => [
                'title' => 'Larakey stopped listening for input',
                'body' => 'Quit and reopen Larakey. If it keeps happening, please report a bug.',
                'command' => null,
            ],
            default => null,
        };
    }
}
