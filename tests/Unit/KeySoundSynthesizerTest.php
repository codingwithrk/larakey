<?php

use App\Audio\KeySoundSynthesizer;

$preset = [
    'hits' => [[0, 1.0], [3, 0.45]],
    'impulse' => 0.6,
    'modes' => [[480, 16, 0.55], [1150, 12, 1.0], [2250, 8, 0.75]],
    'thump' => [170, 18, 0.45],
    'tick' => 0.25,
];

it('renders a valid 16-bit mono wav file', function () use ($preset) {
    $wav = (new KeySoundSynthesizer)->render($preset);

    $header = unpack('a4riff/Vsize/a4wave/a4fmt/VfmtSize/vformat/vchannels/VsampleRate/VbyteRate/vblockAlign/vbits/a4data/VdataSize', $wav);

    expect($header['riff'])->toBe('RIFF')
        ->and($header['wave'])->toBe('WAVE')
        ->and($header['channels'])->toBe(1)
        ->and($header['sampleRate'])->toBe(KeySoundSynthesizer::SAMPLE_RATE)
        ->and($header['bits'])->toBe(16)
        ->and($header['size'])->toBe(strlen($wav) - 8)
        ->and($header['dataSize'])->toBe(strlen($wav) - 44)
        ->and($header['dataSize'])->toBeGreaterThan(0);
});

it('is deterministic for the same seed', function () use ($preset) {
    $synthesizer = new KeySoundSynthesizer;

    expect($synthesizer->render($preset, 'space', 'down', 42))
        ->toBe($synthesizer->render($preset, 'space', 'down', 42))
        ->not->toBe($synthesizer->render($preset, 'space', 'down', 43));
});

it('renders key release sounds quieter than key presses', function () use ($preset) {
    $synthesizer = new KeySoundSynthesizer;

    $peak = function (string $wav): int {
        return max(array_map(abs(...), array_values(unpack('s*', substr($wav, 44)))));
    };

    expect($peak($synthesizer->render($preset, 'default', 'up')))
        ->toBeLessThan($peak($synthesizer->render($preset, 'default', 'down')));
});

it('has separate sound categories for mouse buttons', function () {
    expect((new KeySoundSynthesizer)->categories('mouse'))->toBe(['default', 'right', 'middle']);
});

it('renders louder release clicks for mouse switches than for keys', function () use ($preset) {
    $synthesizer = new KeySoundSynthesizer;

    $peak = function (string $wav): int {
        return max(array_map(abs(...), array_values(unpack('s*', substr($wav, 44)))));
    };

    expect($peak($synthesizer->render([...$preset, 'release_gain' => 0.7], 'default', 'up', 1, 'mouse')))
        ->toBeGreaterThan($peak($synthesizer->render($preset, 'default', 'up')));
});
