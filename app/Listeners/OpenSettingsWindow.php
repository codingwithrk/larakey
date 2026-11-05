<?php

namespace App\Listeners;

use App\Events\OpenSettingsRequested;
use Native\Desktop\Facades\Window;

class OpenSettingsWindow
{
    public function handle(OpenSettingsRequested $event): void
    {
        Window::open('settings')
            ->route('settings.edit')
            ->title('Larakey Settings')
            ->width(460)
            ->height(820)
            ->resizable(false)
            ->maximizable(false)
            ->fullscreenable(false)
            ->hideMenu()
            ->showDevTools(false);
    }
}
