<?php

use App\Events\OpenSettingsRequested;
use App\Events\ToggleSoundsRequested;
use App\Services\KeySoundSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\App;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Settings;

beforeEach(function () {
    $this->packsPath = storage_path('framework/testing/sounds');

    $packs = [
        'keyboard' => ['creamy-linear' => 'Creamy Linear', 'clicky-blue' => 'Clicky Blue'],
        'mouse' => ['classic-mouse' => 'Classic Mouse', 'muted-mouse' => 'Muted Mouse'],
    ];

    foreach ($packs as $device => $devicePacks) {
        foreach ($devicePacks as $slug => $name) {
            File::ensureDirectoryExists("{$this->packsPath}/{$device}/{$slug}");
            File::put("{$this->packsPath}/{$device}/{$slug}/pack.json", json_encode(['name' => $name, 'description' => "{$name} sounds"]));
        }
    }

    config(['larakey.packs_path' => $this->packsPath]);
});

afterEach(function () {
    File::deleteDirectory($this->packsPath);
});

it('shows the settings window with the installed keyboard and mouse packs', function () {
    Settings::shouldReceive('get')->with(KeySoundSettings::STORE_KEY, [])->andReturn([]);
    App::shouldReceive('openAtLogin')->andReturnFalse();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Creamy Linear')
        ->assertSee('Clicky Blue')
        ->assertSee('Classic Mouse')
        ->assertSee('Muted Mouse');
});

it('stores updated settings with the resolved pack path for the sound engine', function () {
    Settings::shouldReceive('get')->andReturn([]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => $settings['pack'] === 'clicky-blue'
            && $settings['volume'] === 30
            && $settings['pack_path'] === "{$this->packsPath}/keyboard/clicky-blue"));

    $this->patchJson(route('settings.update'), ['pack' => 'clicky-blue', 'volume' => 30])
        ->assertOk()
        ->assertJsonPath('settings.pack', 'clicky-blue');
});

it('stores the mouse pack and mouse toggle for the sound engine', function () {
    Settings::shouldReceive('get')->andReturn([]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => $settings['mouse_pack'] === 'muted-mouse'
            && $settings['mouse_enabled'] === false
            && $settings['mouse_pack_path'] === "{$this->packsPath}/mouse/muted-mouse"));

    $this->patchJson(route('settings.update'), ['mouse_pack' => 'muted-mouse', 'mouse_enabled' => false])
        ->assertOk()
        ->assertJsonPath('settings.mouse_pack', 'muted-mouse');
});

it('rejects unknown sound packs and out of range volume', function () {
    Settings::shouldReceive('set')->never();

    $this->patchJson(route('settings.update'), ['pack' => 'missing-pack', 'mouse_pack' => 'clicky-blue', 'volume' => 150])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pack', 'mouse_pack', 'volume']);
});

it('updates launch at login without storing it as a sound setting', function () {
    Settings::shouldReceive('get')->andReturn([]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => ! array_key_exists('launch_at_login', $settings)));
    App::shouldReceive('openAtLogin')->once()->with(true);

    $this->patchJson(route('settings.update'), ['launch_at_login' => true])->assertOk();
});

it('toggles sounds and refreshes the tray menu when the shortcut is pressed', function () {
    Settings::shouldReceive('get')->andReturn(['enabled' => true]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => $settings['enabled'] === false));
    MenuBar::shouldReceive('contextMenu')->once();

    ToggleSoundsRequested::dispatch();
});

it('opens the settings window without developer tools even in debug mode', function () {
    config(['app.debug' => true]);
    Http::fake();

    OpenSettingsRequested::dispatch();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'window/open')
        && $request['id'] === 'settings'
        && $request['showDevTools'] === false);
});

it('toggles sounds when the global shortcut fires from electron', function () {
    Settings::shouldReceive('get')->andReturn(['enabled' => true]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => $settings['enabled'] === false));
    MenuBar::shouldReceive('contextMenu')->once();

    $this->postJson('/_native/api/events', [
        'event' => ToggleSoundsRequested::class,
        'payload' => [config('larakey.toggle_shortcut')],
    ])->assertOk();
});

it('toggles sounds when the tray menu item is clicked', function () {
    Settings::shouldReceive('get')->andReturn(['enabled' => true]);
    Settings::shouldReceive('set')->once();
    MenuBar::shouldReceive('contextMenu')->once();

    $this->postJson('/_native/api/events', [
        'event' => ToggleSoundsRequested::class,
        'payload' => ['item' => ['id' => null, 'label' => 'Keyboard Sounds', 'checked' => false], 'combo' => []],
    ])->assertOk();
});
