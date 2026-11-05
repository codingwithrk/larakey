<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sound Packs Path
    |--------------------------------------------------------------------------
    |
    | Keyboard packs live in "keyboard/" and mouse packs in "mouse/". Every
    | sub-directory containing a "pack.json" manifest is a sound pack. The
    | Electron sound engine reads the WAV files from here directly.
    |
    */

    'packs_path' => public_path('sounds'),

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'enabled' => true,
        'volume' => 60,
        'pack' => 'mechanical',
        'mouse_enabled' => true,
        'mouse_pack' => 'classic-mouse',
        'key_up' => true,
        'pitch_variation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mute Shortcut
    |--------------------------------------------------------------------------
    */

    'toggle_shortcut' => env('LARAKEY_TOGGLE_SHORTCUT', 'CmdOrCtrl+Shift+K'),

    /*
    |--------------------------------------------------------------------------
    | Synthesized Sound Pack Presets
    |--------------------------------------------------------------------------
    |
    | Recipes used by "php artisan larakey:generate-sounds" to synthesize the
    | bundled packs with modal synthesis:
    |
    | - hits:    [time ms, amplitude] impacts (bottom-out, bump, click jacket)
    | - impulse: decay in ms of each impact's noise burst (lower = sharper)
    | - modes:   [frequency Hz, decay ms, gain] resonances of keycap, plate and case
    | - thump:   [frequency Hz, decay ms, gain] low body of the case
    | - tick:    amount of raw high-frequency transient
    |
    */

    'presets' => [
        'keyboard' => [
            'mechanical' => [
                'name' => 'Classic Mechanical',
                'description' => 'Linear switches in a plastic case, the familiar office mechanical.',
                'hits' => [[0, 1.0], [3, 0.45]],
                'impulse' => 0.6,
                'modes' => [[480, 16, 0.55], [1150, 12, 1.0], [2250, 8, 0.75], [3550, 5, 0.45], [5300, 3, 0.25]],
                'thump' => [170, 18, 0.45],
                'tick' => 0.25,
            ],
            'creamy-linear' => [
                'name' => 'Creamy Linear',
                'description' => 'Lubed linears on a gasket mount: deep, muted thock.',
                'hits' => [[0, 1.0], [2.5, 0.3]],
                'impulse' => 1.0,
                'modes' => [[290, 24, 1.0], [700, 16, 0.75], [1450, 9, 0.35], [2600, 5, 0.15]],
                'thump' => [130, 32, 0.8],
                'tick' => 0.08,
            ],
            'tactile-brown' => [
                'name' => 'Tactile Brown',
                'description' => 'Soft bump on the way down, then a rounded clack.',
                'hits' => [[0, 0.3], [7, 1.0], [10, 0.35]],
                'impulse' => 0.7,
                'modes' => [[430, 18, 0.7], [1000, 13, 1.0], [2050, 8, 0.6], [3300, 5, 0.35]],
                'thump' => [190, 22, 0.5],
                'tick' => 0.18,
            ],
            'clicky-blue' => [
                'name' => 'Clicky Blue',
                'description' => 'Crisp click jacket followed by a sharp bottom-out.',
                'hits' => [[0, 0.9], [1.2, 0.7], [8, 1.0], [11, 0.4]],
                'impulse' => 0.3,
                'modes' => [[850, 10, 0.55], [2200, 8, 1.0], [4300, 5, 0.95], [6200, 3, 0.55]],
                'thump' => [220, 14, 0.3],
                'tick' => 0.45,
            ],
            'box-jade' => [
                'name' => 'Box Jade',
                'description' => 'Thick, loud click bar with a heavy bottom-out.',
                'hits' => [[0, 1.0], [0.8, 0.85], [6, 0.9], [8.5, 0.35]],
                'impulse' => 0.35,
                'modes' => [[700, 12, 0.6], [1600, 10, 0.9], [3100, 7, 1.0], [5000, 4, 0.6]],
                'thump' => [200, 20, 0.5],
                'tick' => 0.4,
            ],
            'buckling-spring' => [
                'name' => 'Buckling Spring',
                'description' => 'Vintage spring buckle with a ringing ping and loud clack.',
                'hits' => [[0, 1.0], [1.6, 0.6], [9, 0.8]],
                'impulse' => 0.45,
                'modes' => [[380, 22, 0.7], [1050, 16, 0.9], [2300, 10, 0.7], [3150, 45, 0.3], [4700, 6, 0.4]],
                'thump' => [150, 30, 0.6],
                'tick' => 0.3,
            ],
            'low-profile' => [
                'name' => 'Low Profile',
                'description' => 'Short-travel switches with a tight, thin clack.',
                'hits' => [[0, 1.0], [1.8, 0.35]],
                'impulse' => 0.4,
                'modes' => [[900, 8, 0.7], [2000, 6, 1.0], [3800, 4, 0.6], [5600, 2.5, 0.3]],
                'thump' => [260, 10, 0.25],
                'tick' => 0.22,
            ],
            'silent-linear' => [
                'name' => 'Silent Linear',
                'description' => 'Dampened stems for a soft, quiet patter.',
                'hits' => [[0, 1.0]],
                'impulse' => 1.6,
                'modes' => [[320, 12, 1.0], [800, 8, 0.5], [1700, 5, 0.2]],
                'thump' => [150, 16, 0.6],
                'tick' => 0.02,
                'release_gain' => 0.25,
            ],
            'keychron-k-red' => [
                'name' => 'Keychron-style K Red',
                'description' => 'K-series feel: smooth Gateron Reds in a hollow frame with a little case ring.',
                'hits' => [[0, 1.0], [2.8, 0.45]],
                'impulse' => 0.55,
                'modes' => [[520, 18, 0.6], [940, 32, 0.45], [1250, 13, 1.0], [2400, 9, 0.7], [3800, 38, 0.12], [5200, 3, 0.25]],
                'thump' => [190, 20, 0.4],
                'tick' => 0.22,
            ],
            'keychron-k-brown' => [
                'name' => 'Keychron-style K Brown',
                'description' => 'K-series feel: tactile Gateron Browns with a hollow, airy clack.',
                'hits' => [[0, 0.3], [6.5, 1.0], [9.5, 0.4]],
                'impulse' => 0.65,
                'modes' => [[470, 18, 0.65], [920, 30, 0.45], [1100, 13, 1.0], [2200, 8, 0.6], [3800, 36, 0.1]],
                'thump' => [190, 22, 0.45],
                'tick' => 0.18,
            ],
            'keychron-k-blue' => [
                'name' => 'Keychron-style K Blue',
                'description' => 'K-series feel: clicky Gateron Blues ringing through a hollow case.',
                'hits' => [[0, 0.9], [1.1, 0.7], [7.5, 1.0], [10.5, 0.4]],
                'impulse' => 0.3,
                'modes' => [[880, 12, 0.5], [960, 30, 0.35], [2250, 8, 1.0], [4300, 5, 0.9], [6100, 3, 0.5]],
                'thump' => [210, 15, 0.3],
                'tick' => 0.42,
            ],
            'keychron-q-thock' => [
                'name' => 'Keychron-style Q Thock',
                'description' => 'Q-series feel: aluminum gasket board with foam, deep and poppy.',
                'hits' => [[0, 1.0], [2.2, 0.35]],
                'impulse' => 0.85,
                'modes' => [[260, 26, 1.0], [620, 18, 0.8], [1300, 10, 0.45], [1950, 24, 0.08], [2700, 5, 0.18]],
                'thump' => [120, 36, 0.85],
                'tick' => 0.1,
            ],
        ],

        'mouse' => [
            'classic-mouse' => [
                'name' => 'Classic Mouse',
                'description' => 'Standard micro switch click, crisp on press and release.',
                'hits' => [[0, 1.0]],
                'impulse' => 0.2,
                'modes' => [[1800, 6, 0.6], [3400, 5, 1.0], [5600, 3, 0.8], [7800, 2, 0.4]],
                'thump' => [420, 6, 0.2],
                'tick' => 0.5,
                'release_gain' => 0.7,
            ],
            'gaming-mouse' => [
                'name' => 'Gaming Mouse',
                'description' => 'Tight, high-tension switches with a sharp snap.',
                'hits' => [[0, 1.0], [0.6, 0.5]],
                'impulse' => 0.15,
                'modes' => [[2400, 5, 0.6], [4300, 4, 1.0], [6600, 2.5, 0.8], [8600, 1.5, 0.4]],
                'thump' => [500, 5, 0.15],
                'tick' => 0.6,
                'release_gain' => 0.75,
            ],
            'heavy-click' => [
                'name' => 'Heavy Click',
                'description' => 'Older, larger shell with a deeper plastic clack.',
                'hits' => [[0, 1.0], [1.2, 0.4]],
                'impulse' => 0.3,
                'modes' => [[900, 10, 0.8], [2100, 8, 1.0], [4200, 5, 0.6], [6400, 3, 0.3]],
                'thump' => [250, 14, 0.5],
                'tick' => 0.35,
                'release_gain' => 0.6,
            ],
            'muted-mouse' => [
                'name' => 'Muted Mouse',
                'description' => 'Soft, dampened clicks that stay in the background.',
                'hits' => [[0, 1.0]],
                'impulse' => 0.6,
                'modes' => [[1200, 5, 1.0], [2600, 4, 0.6], [4400, 2.5, 0.25]],
                'thump' => [300, 8, 0.4],
                'tick' => 0.1,
                'release_gain' => 0.5,
            ],
        ],
    ],

];
