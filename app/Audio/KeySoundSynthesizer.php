<?php

namespace App\Audio;

/**
 * Synthesizes mechanical keyboard and mouse sounds as 16-bit mono WAV files.
 *
 * Uses modal synthesis: a short noise impulse (the stem bottoming out) excites a set
 * of damped resonators that model the keycap, plate and case. Several impulses can be
 * layered to model tactile bumps, click jackets and stabilizer rattle, and a low
 * "thump" adds the body of the case.
 */
class KeySoundSynthesizer
{
    public const SAMPLE_RATE = 44100;

    /**
     * Per-device, per-category adjustments applied on top of a preset. Extra hits
     * model the stabilizer rattle of the larger keys.
     *
     * @var array<string, array<string, array{frequency: float, decay: float, gain: float, hits: list<array{0: float, 1: float}>}>>
     */
    protected const CATEGORIES = [
        'keyboard' => [
            'default' => ['frequency' => 1.0, 'decay' => 1.0, 'gain' => 1.0, 'hits' => []],
            'space' => ['frequency' => 0.78, 'decay' => 1.4, 'gain' => 1.0, 'hits' => [[14, 0.35], [23, 0.18]]],
            'enter' => ['frequency' => 0.86, 'decay' => 1.2, 'gain' => 1.0, 'hits' => [[12, 0.25]]],
            'backspace' => ['frequency' => 0.9, 'decay' => 1.1, 'gain' => 0.95, 'hits' => [[11, 0.2]]],
            'modifier' => ['frequency' => 0.95, 'decay' => 1.0, 'gain' => 0.9, 'hits' => []],
        ],
        'mouse' => [
            'default' => ['frequency' => 1.0, 'decay' => 1.0, 'gain' => 1.0, 'hits' => []],
            'right' => ['frequency' => 1.04, 'decay' => 1.0, 'gain' => 0.95, 'hits' => []],
            'middle' => ['frequency' => 0.82, 'decay' => 1.2, 'gain' => 0.85, 'hits' => []],
        ],
    ];

    /**
     * @return list<string>
     */
    public function categories(string $device = 'keyboard'): array
    {
        return array_keys(self::CATEGORIES[$device]);
    }

    /**
     * Render a single key or button sound as WAV file contents.
     *
     * @param  array{hits: list<array{0: float|int, 1: float|int}>, impulse: float|int, modes: list<array{0: float|int, 1: float|int, 2: float|int}>, thump: array{0: float|int, 1: float|int, 2: float|int}, tick: float|int, release_gain?: float|int}  $preset
     */
    public function render(array $preset, string $category = 'default', string $phase = 'down', int $seed = 1, string $device = 'keyboard'): string
    {
        mt_srand($seed);

        $categories = self::CATEGORIES[$device] ?? self::CATEGORIES['keyboard'];
        $category = $categories[$category] ?? $categories['default'];
        $isRelease = $phase === 'up';
        $releaseGain = $preset['release_gain'] ?? 0.4;

        $frequencyScale = $category['frequency'] * ($isRelease ? 1.12 : 1.0) * $this->jitter(0.03);
        $decayScale = $category['decay'] * ($isRelease ? 0.7 : 1.0);
        $hits = $isRelease ? [[0, 1.0]] : [...$preset['hits'], ...$category['hits']];
        $impulseDecay = max(0.1, $preset['impulse'] * ($isRelease ? 0.7 : 1.0)) / 1000;

        $modes = array_map(fn (array $mode): array => [
            $mode[0] * $frequencyScale * $this->jitter(0.04),
            max(0.5, $mode[1] * $decayScale) / 1000,
            $mode[2] * $this->jitter(0.15),
        ], $preset['modes']);

        [$thumpFrequency, $thumpDecay, $thumpGain] = $preset['thump'];
        $thumpFrequency *= $frequencyScale;
        $thumpDecay = max(0.5, $thumpDecay * $decayScale) / 1000;
        $thumpGain *= $isRelease ? 0.3 : 1.0;

        $longestDecay = max([$thumpDecay, ...array_column($modes, 1)]);
        $lastHit = max(array_column($hits, 0)) / 1000;
        $length = (int) round(min(0.5, $lastHit + $longestDecay * 6 + 0.01) * self::SAMPLE_RATE);

        $excitation = $this->excitation($hits, $impulseDecay, $length);

        $samples = array_fill(0, $length, 0.0);

        foreach ($modes as [$frequency, $decay, $gain]) {
            $this->addResonator($samples, $excitation, $frequency, $decay, $gain);
        }

        $previous = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $time = $i / self::SAMPLE_RATE;

            $thump = $thumpGain * sin(2 * M_PI * $thumpFrequency * $time) * exp(-$time / $thumpDecay) * $this->hitEnvelope($hits, $time);
            $tick = ($excitation[$i] - $previous) * $preset['tick'];
            $previous = $excitation[$i];

            $samples[$i] += $thump + $tick;
        }

        return $this->encodeWav($this->shape($samples, $category['gain'] * ($isRelease ? $releaseGain : 1.0)));
    }

    /**
     * Noise bursts at each hit time, decaying exponentially.
     *
     * @param  list<array{0: float|int, 1: float|int}>  $hits
     * @return list<float>
     */
    protected function excitation(array $hits, float $impulseDecay, int $length): array
    {
        $excitation = array_fill(0, $length, 0.0);
        $burstLength = (int) ($impulseDecay * 8 * self::SAMPLE_RATE);

        foreach ($hits as [$offset, $amplitude]) {
            $start = (int) round($offset / 1000 * self::SAMPLE_RATE);
            $amplitude *= $this->jitter(0.1);

            for ($i = 0; $i < $burstLength && $start + $i < $length; $i++) {
                $white = (mt_rand() / mt_getrandmax()) * 2 - 1;
                $excitation[$start + $i] += $white * $amplitude * exp(-($i / self::SAMPLE_RATE) / $impulseDecay);
            }
        }

        return $excitation;
    }

    /**
     * Run the excitation through a two-pole resonator and mix it into the output.
     *
     * @param  list<float>  $samples
     * @param  list<float>  $excitation
     */
    protected function addResonator(array &$samples, array $excitation, float $frequency, float $decay, float $gain): void
    {
        $radius = exp(-1 / ($decay * self::SAMPLE_RATE));
        $omega = 2 * M_PI * min($frequency, self::SAMPLE_RATE * 0.45) / self::SAMPLE_RATE;
        $feedback = 2 * $radius * cos($omega);
        $damping = $radius * $radius;

        $previous = 0.0;
        $beforePrevious = 0.0;
        $outputs = [];

        foreach ($excitation as $input) {
            $output = $feedback * $previous - $damping * $beforePrevious + $input;
            $beforePrevious = $previous;
            $previous = $output;
            $outputs[] = $output;
        }

        // Normalize each mode to its own peak so preset gains directly set the mix.
        $scale = $gain / (max(array_map(abs(...), $outputs)) ?: 1.0);

        foreach ($outputs as $i => $output) {
            $samples[$i] += $output * $scale;
        }
    }

    /**
     * Strongest hit that has already happened, so the thump restarts with each impact.
     *
     * @param  list<array{0: float|int, 1: float|int}>  $hits
     */
    protected function hitEnvelope(array $hits, float $time): float
    {
        $envelope = 0.0;

        foreach ($hits as [$offset, $amplitude]) {
            if ($time >= $offset / 1000) {
                $envelope = max($envelope, $amplitude);
            }
        }

        return $envelope;
    }

    protected function jitter(float $amount): float
    {
        return 1 + ((mt_rand(0, 2000) / 1000) - 1) * $amount;
    }

    /**
     * Apply a short fade out and normalize to the given peak gain.
     *
     * @param  list<float>  $samples
     * @return list<float>
     */
    protected function shape(array $samples, float $gain): array
    {
        $count = count($samples);
        $fadeOut = (int) (0.008 * self::SAMPLE_RATE);

        for ($i = max(0, $count - $fadeOut); $i < $count; $i++) {
            $samples[$i] *= ($count - $i) / $fadeOut;
        }

        $peak = max(array_map(abs(...), $samples)) ?: 1.0;
        $scale = (0.9 * $gain) / $peak;

        return array_map(fn (float $sample): float => $sample * $scale, $samples);
    }

    /**
     * @param  list<float>  $samples
     */
    protected function encodeWav(array $samples): string
    {
        $data = '';

        foreach ($samples as $sample) {
            $data .= pack('v', (int) round(max(-1, min(1, $sample)) * 32767) & 0xFFFF);
        }

        $byteRate = self::SAMPLE_RATE * 2;

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, self::SAMPLE_RATE, $byteRate, 2, 16)
            .'data'.pack('V', strlen($data)).$data;
    }
}
