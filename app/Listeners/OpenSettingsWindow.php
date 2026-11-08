<?php

namespace App\Listeners;

use App\Events\OpenSettingsRequested;
use App\Support\InputStatus;
use Native\Desktop\Facades\Window;

class OpenSettingsWindow
{
    /**
     * Window height that fits the settings, including the title bar.
     */
    public const HEIGHT = 270;

    /**
     * Extra height for the banner explaining how to give Larakey input access.
     */
    public const INPUT_PROBLEM_HEIGHT = 150;

    public function __construct(protected InputStatus $inputStatus) {}

    public function handle(OpenSettingsRequested $event): void
    {
        $height = $this->inputStatus->problem() ? self::HEIGHT + self::INPUT_PROBLEM_HEIGHT : self::HEIGHT;

        Window::open('settings')
            ->route('settings.edit')
            ->title('Larakey Settings')
            ->width(390)
            ->height($height)
            ->resizable(false)
            ->maximizable(false)
            ->fullscreenable(false)
            ->hideMenu()
            ->showDevTools(false);
    }
}
