<?php

use App\Events\OpenSettingsRequested;
use App\Events\ToggleSoundsRequested;
use App\Listeners\OpenSettingsWindow;
use App\Providers\NativeAppServiceProvider;
use App\Services\KeySoundSettings;
use App\Support\InputStatus;
use App\Support\Platform;
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

    // CI runs on Linux, so pin the platform rather than inheriting the test machine's.
    $this->app->instance(Platform::class, new Platform('Darwin'));
});

afterEach(function () {
    File::deleteDirectory($this->packsPath);
});

it('shows the settings window with the installed keyboard and mouse packs', function () {
    Settings::shouldReceive('get')->andReturn([]);
    App::shouldReceive('openAtLogin')->andReturnFalse();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Creamy Linear')
        ->assertSee('Clicky Blue')
        ->assertSee('Classic Mouse')
        ->assertSee('Muted Mouse')
        ->assertSeeInOrder(['<select', 'name="pack"', 'value="creamy-linear"', '<select', 'name="mouse_pack"', 'value="classic-mouse"'], false)
        ->assertDontSee('role="alert"', false);
});

it('explains how to grant input access when the engine cannot read devices on Linux', function () {
    Settings::shouldReceive('get')->with(KeySoundSettings::STORE_KEY, [])->andReturn([]);
    Settings::shouldReceive('get')->with(InputStatus::STORE_KEY, [])->andReturn([
        'platform' => 'linux',
        'session' => 'wayland',
        'backend' => 'evdev',
        'problem' => 'input-permission',
    ]);
    App::shouldReceive('openAtLogin')->andReturnFalse();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Larakey can&#039;t read your keyboard and mouse', false)
        ->assertSee('sudo usermod -aG input $USER');
});

it('asks macOS users to allow Accessibility access when it is missing', function () {
    Settings::shouldReceive('get')->with(KeySoundSettings::STORE_KEY, [])->andReturn([]);
    Settings::shouldReceive('get')->with(InputStatus::STORE_KEY, [])->andReturn(['problem' => 'accessibility']);
    App::shouldReceive('openAtLogin')->andReturnFalse();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Privacy &amp; Security → Accessibility', false)
        ->assertDontSee('usermod');
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

it('uses the system login items for launch at login on macOS and Windows', function () {
    Settings::shouldReceive('get')->andReturn([]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => $settings['launch_at_login'] === false));
    App::shouldReceive('openAtLogin')->once()->with(true);

    $this->patchJson(route('settings.update'), ['launch_at_login' => true])->assertOk();
});

it('stores launch at login for the engine autostart entry on Linux', function () {
    $this->app->instance(Platform::class, new Platform('Linux'));

    Settings::shouldReceive('get')->andReturn([]);
    Settings::shouldReceive('set')
        ->once()
        ->with(KeySoundSettings::STORE_KEY, Mockery::on(fn (array $settings): bool => $settings['launch_at_login'] === true));
    App::shouldReceive('openAtLogin')->never();

    $this->patchJson(route('settings.update'), ['launch_at_login' => true])->assertOk();
});

it('reads launch at login from the stored settings on Linux', function () {
    $this->app->instance(Platform::class, new Platform('Linux'));

    Settings::shouldReceive('get')->with(KeySoundSettings::STORE_KEY, [])->andReturn(['launch_at_login' => true]);
    Settings::shouldReceive('get')->with(InputStatus::STORE_KEY, [])->andReturn([]);
    App::shouldReceive('openAtLogin')->never();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertSeeInOrder(['name="launch_at_login" value="1"', 'checked', 'Launch at login'], false);
});

it('uses a full-color tray icon on Linux and a template icon on macOS', function () {
    $this->app->instance(Platform::class, new Platform('Linux'));
    expect(app(NativeAppServiceProvider::class)->trayIcon())->toBe(public_path('menuBarIconLinux.png'))->toBeFile();

    $this->app->instance(Platform::class, new Platform('Darwin'));
    expect(app(NativeAppServiceProvider::class)->trayIcon())->toBe(public_path('menuBarIconTemplate.png'))->toBeFile();
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

it('sizes the settings window to fit the input access banner when it is shown', function (?string $problem, int $height) {
    Settings::shouldReceive('get')->with(InputStatus::STORE_KEY, [])->andReturn(['problem' => $problem]);
    Http::fake();

    OpenSettingsRequested::dispatch();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'window/open')
        && $request['width'] === 390
        && $request['height'] === $height);
})->with([
    'no problem' => [null, OpenSettingsWindow::HEIGHT],
    'input permission missing' => ['input-permission', OpenSettingsWindow::HEIGHT + OpenSettingsWindow::INPUT_PROBLEM_HEIGHT],
]);
