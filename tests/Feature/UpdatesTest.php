<?php

use App\Events\CheckForUpdatesRequested;
use App\Jobs\NotifyAboutUpdate;
use App\Support\Platform;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Settings;

beforeEach(function () {
    config([
        'nativephp.version' => '1.0.0',
        'nativephp.updater.providers.github.owner' => 'codingwithrk',
        'nativephp.updater.providers.github.repo' => 'larakey',
    ]);

    $this->app->instance(Platform::class, new Platform('Darwin'));
});

/**
 * Fake GitHub's latest release and NativePHP's Electron API, where the alert's
 * chosen button index is returned as "result".
 */
function fakeUpdateCheck(mixed $latestRelease, int $chosenButton = 0): void
{
    Http::fake([
        'api.github.com/*' => $latestRelease,
        '*/alert/message' => Http::response(['result' => $chosenButton]),
        '*' => Http::response(),
    ]);
}

function clickCheckForUpdates(): void
{
    test()->postJson('/_native/api/events', [
        'event' => CheckForUpdatesRequested::class,
        'payload' => ['item' => ['id' => null, 'label' => 'Check for Updates…'], 'combo' => []],
    ])->assertOk();
}

function newRelease(string $tag = 'v1.1.0'): array
{
    return ['tag_name' => $tag, 'html_url' => "https://github.com/codingwithrk/larakey/releases/tag/{$tag}"];
}

it('opens the release page on macOS and Linux when a newer version is available', function (string $family) {
    $this->app->instance(Platform::class, new Platform($family));
    fakeUpdateCheck(Http::response(newRelease()), chosenButton: 0);

    clickCheckForUpdates();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'alert/message')
        && $request['message'] === 'Larakey 1.1.0 is available'
        && $request['buttons'] === ['Download', 'Later']);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'shell/open-external')
        && $request['url'] === 'https://github.com/codingwithrk/larakey/releases/tag/v1.1.0');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'auto-updater'));
})->with(['Darwin', 'Linux']);

it('does nothing more when the user chooses later', function () {
    fakeUpdateCheck(Http::response(newRelease()), chosenButton: 1);

    clickCheckForUpdates();

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), 'shell/open-external'));
});

it('lets the auto-updater install the new version on Windows', function () {
    $this->app->instance(Platform::class, new Platform('Windows'));
    fakeUpdateCheck(Http::response(newRelease()), chosenButton: 0);

    clickCheckForUpdates();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'alert/message')
        && $request['buttons'] === ['Update', 'Later']);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'auto-updater/check-for-updates'));
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), 'shell/open-external'));
});

it('says the app is up to date when the latest release is not newer', function (string $tag) {
    fakeUpdateCheck(Http::response(newRelease($tag)));

    clickCheckForUpdates();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'alert/message')
        && $request['message'] === 'You’re up to date'
        && $request['detail'] === 'Larakey 1.0.0 is the latest version.');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), 'shell/open-external'));
})->with(['same version' => 'v1.0.0', 'older version' => 'v0.9.0']);

it('explains when GitHub cannot be reached', function (mixed $response) {
    fakeUpdateCheck($response);

    clickCheckForUpdates();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'alert/message')
        && $request['message'] === 'Couldn’t check for updates');
})->with([
    'server error' => fn () => Http::response(status: 500),
    'no releases yet' => fn () => Http::response(['message' => 'Not Found'], 404),
    'offline' => fn () => Http::failedConnection(),
]);

it('notifies once at startup when a newer version is available', function () {
    fakeUpdateCheck(Http::response(newRelease()));
    Settings::shouldReceive('get')->with(NotifyAboutUpdate::NOTIFIED_KEY)->andReturnNull();
    Settings::shouldReceive('set')->once()->with(NotifyAboutUpdate::NOTIFIED_KEY, '1.1.0');

    NotifyAboutUpdate::dispatchSync();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/notification')
        && $request['title'] === 'Larakey 1.1.0 is available'
        && $request['event'] === CheckForUpdatesRequested::class);
});

it('does not notify about the same version twice', function () {
    fakeUpdateCheck(Http::response(newRelease()));
    Settings::shouldReceive('get')->with(NotifyAboutUpdate::NOTIFIED_KEY)->andReturn('1.1.0');
    Settings::shouldReceive('set')->never();

    NotifyAboutUpdate::dispatchSync();

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/notification'));
});

it('stays quiet at startup when up to date or offline', function (mixed $response) {
    fakeUpdateCheck($response);
    Settings::shouldReceive('get')->andReturnNull();
    Settings::shouldReceive('set')->never();

    NotifyAboutUpdate::dispatchSync();

    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/notification')
        || str_ends_with($request->url(), 'alert/message'));
})->with([
    'up to date' => fn () => Http::response(newRelease('v1.0.0')),
    'offline' => fn () => Http::failedConnection(),
]);

it('opens the update dialog when the notification is clicked', function () {
    fakeUpdateCheck(Http::response(newRelease()), chosenButton: 1);

    test()->postJson('/_native/api/events', [
        'event' => CheckForUpdatesRequested::class,
        'payload' => ['reference' => 'abc', 'event' => '{}'],
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'alert/message')
        && $request['message'] === 'Larakey 1.1.0 is available');
});
