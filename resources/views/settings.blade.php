<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Larakey Settings</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-50 text-zinc-900 antialiased select-none dark:bg-zinc-950 dark:text-zinc-100">
        <form
            id="settings-form"
            method="POST"
            action="{{ route('settings.update') }}"
            data-check-updates-url="{{ route('settings.check-for-updates') }}"
            class="flex flex-col gap-6 p-6"
        >
            @csrf
            @method('PATCH')

            <header class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Larakey</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Mechanical keyboard and mouse sounds, everywhere.</p>
                </div>

                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" class="peer sr-only" @checked($settings['enabled'])>
                    <span class="h-7 w-12 rounded-full bg-zinc-300 transition peer-checked:bg-emerald-500 dark:bg-zinc-700"></span>
                    <span class="absolute left-1 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    <span class="sr-only">Enable keyboard sounds</span>
                </label>
            </header>

            @if ($inputProblem)
                <section role="alert" class="flex flex-col gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    <h2 class="font-semibold">{{ $inputProblem['title'] }}</h2>
                    <p class="text-xs leading-relaxed">{{ $inputProblem['body'] }}</p>
                    @if ($inputProblem['command'])
                        <code class="rounded-md bg-amber-100 px-2 py-1 font-mono text-xs select-text dark:bg-black/30">{{ $inputProblem['command'] }}</code>
                    @endif
                </section>
            @endif

            <section class="flex flex-col gap-2">
                <div class="flex items-center justify-between text-sm font-medium">
                    <label for="volume">Volume</label>
                    <span id="volume-value" class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $settings['volume'] }}%</span>
                </div>
                <input id="volume" type="range" name="volume" min="0" max="100" value="{{ $settings['volume'] }}" class="w-full accent-emerald-500">
            </section>

            <section class="flex flex-col gap-2">
                <h2 class="text-sm font-medium">Keyboard switches</h2>
                <x-sound-pack-picker name="pack" :packs="$packs" :selected="$settings['pack']" />
            </section>

            <section class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-sm font-medium">Mouse clicks</h2>
                    <label class="flex cursor-pointer items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <input type="hidden" name="mouse_enabled" value="0">
                        <input type="checkbox" name="mouse_enabled" value="1" class="size-4 accent-emerald-500" @checked($settings['mouse_enabled'])>
                        Play mouse sounds
                    </label>
                </div>
                <x-sound-pack-picker name="mouse_pack" :packs="$mousePacks" :selected="$settings['mouse_pack']" />
            </section>

            <section class="flex flex-col divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                @foreach ([
                    'key_up' => ['Key release sounds', 'Play a softer sound when a keyboard key is released.', $settings['key_up']],
                    'pitch_variation' => ['Natural variation', 'Slightly vary pitch so repeated keys never sound robotic.', $settings['pitch_variation']],
                    'launch_at_login' => ['Launch at login', 'Start Larakey automatically when you log in.', $launchAtLogin],
                ] as $name => [$label, $hint, $checked])
                    <label class="flex cursor-pointer items-center justify-between gap-4 p-3">
                        <span class="flex flex-col">
                            <span class="font-medium">{{ $label }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</span>
                        </span>
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="size-4 accent-emerald-500" @checked($checked)>
                    </label>
                @endforeach
            </section>

            <section class="flex flex-col gap-2">
                <label for="playground" class="text-sm font-medium">Try it</label>
                <textarea id="playground" rows="3" placeholder="Type or click to hear your keyboard and mouse…" class="w-full resize-none rounded-xl border border-zinc-200 bg-white p-3 text-sm outline-none select-text focus:border-emerald-500 dark:border-zinc-800 dark:bg-zinc-900"></textarea>
            </section>

            <footer class="flex items-center justify-between gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                <span>
                    Toggle sounds with <kbd class="rounded border border-zinc-300 px-1 font-mono dark:border-zinc-700">{{ $shortcut }}</kbd>
                </span>
                <span class="flex items-center gap-3">
                    <span>v{{ $version }}</span>
                    <button type="button" id="check-updates" class="rounded-md px-2 py-1 font-medium text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400">Check for updates</button>
                </span>
            </footer>

            <noscript>
                <button type="submit" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-white">Save</button>
            </noscript>
        </form>
    </body>
</html>
