<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scheme-light-dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Larakey Settings</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-100 text-zinc-900 antialiased select-none dark:bg-[#1f2228] dark:text-zinc-100">
        <form
            id="settings-form"
            method="POST"
            action="{{ route('settings.update') }}"
            class="flex flex-col gap-4 p-4 text-sm"
        >
            @csrf
            @method('PATCH')

            @if ($inputProblem)
                <section role="alert" class="flex flex-col gap-1.5 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                    <h2 class="font-semibold">{{ $inputProblem['title'] }}</h2>
                    <p class="leading-relaxed">{{ $inputProblem['body'] }}</p>
                    @if ($inputProblem['command'])
                        <code class="rounded bg-amber-100 px-2 py-1 font-mono select-text dark:bg-black/30">{{ $inputProblem['command'] }}</code>
                    @endif
                </section>
            @endif

            <section class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-3 text-zinc-500 dark:text-zinc-400">
                <label for="pack">Keyboard</label>
                <x-sound-pack-select id="pack" name="pack" :packs="$packs" :selected="$settings['pack']" />

                <label for="mouse_pack">Mouse</label>
                <x-sound-pack-select id="mouse_pack" name="mouse_pack" :packs="$mousePacks" :selected="$settings['mouse_pack']" />

                <label for="volume">Volume</label>
                <input id="volume" type="range" name="volume" min="0" max="100" value="{{ $settings['volume'] }}" style="--fill: {{ $settings['volume'] }}%" class="range-slider w-32 cursor-pointer justify-self-end">
            </section>

            <hr class="border-zinc-300 dark:border-zinc-700">

            <section class="grid grid-cols-2 gap-x-4 gap-y-3">
                @foreach ([
                    'key_up' => ['Key release', $settings['key_up']],
                    'pitch_variation' => ['Pitch variation', $settings['pitch_variation']],
                    'mouse_enabled' => ['Mouse sounds', $settings['mouse_enabled']],
                    'launch_at_login' => ['Launch at login', $launchAtLogin],
                ] as $name => [$label, $checked])
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="size-4 accent-blue-500" @checked($checked)>
                        {{ $label }}
                    </label>
                @endforeach
            </section>

            <noscript>
                <button type="submit" class="rounded-md bg-blue-500 px-3 py-1.5 font-medium text-white">Save</button>
            </noscript>
        </form>
    </body>
</html>
