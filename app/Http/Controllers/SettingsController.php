<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\KeySoundSettings;
use App\Services\SoundPackRepository;
use App\Support\TrayMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Native\Desktop\Facades\App;
use Native\Desktop\Facades\AutoUpdater;

class SettingsController extends Controller
{
    public function edit(KeySoundSettings $settings, SoundPackRepository $packs): View
    {
        return view('settings', [
            'settings' => $settings->all(),
            'packs' => $packs->all(SoundPackRepository::KEYBOARD),
            'mousePacks' => $packs->all(SoundPackRepository::MOUSE),
            'launchAtLogin' => App::openAtLogin(),
            'shortcut' => config('larakey.toggle_shortcut'),
            'version' => config('nativephp.version'),
        ]);
    }

    public function update(UpdateSettingsRequest $request, KeySoundSettings $settings, TrayMenu $trayMenu): JsonResponse|RedirectResponse
    {
        $values = $request->safe()->except('launch_at_login');

        if ($request->has('launch_at_login')) {
            App::openAtLogin($request->boolean('launch_at_login'));
        }

        $updated = $settings->update($values);

        if (array_key_exists('enabled', $values)) {
            $trayMenu->refresh();
        }

        if ($request->wantsJson()) {
            return response()->json(['settings' => $updated]);
        }

        return to_route('settings.edit');
    }

    public function checkForUpdates(): JsonResponse
    {
        AutoUpdater::checkForUpdates();

        return response()->json(['checking' => true]);
    }
}
