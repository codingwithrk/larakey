<?php

namespace App\Console\Commands;

use App\Audio\KeySoundSynthesizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Signature('larakey:generate-sounds {--variants=4 : Number of sound variants per key category}')]
#[Description('Synthesize the bundled keyboard sound packs from the presets in config/larakey.php')]
class GenerateSoundPacks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KeySoundSynthesizer $synthesizer, Filesystem $files): int
    {
        $variants = max(1, (int) $this->option('variants'));
        $packsPath = config('larakey.packs_path');

        $files->ensureDirectoryExists($packsPath);
        $files->cleanDirectory($packsPath);

        foreach (config('larakey.presets') as $device => $presets) {
            foreach ($presets as $slug => $preset) {
                $this->generatePack($synthesizer, $files, $device, $slug, $preset, $packsPath.DIRECTORY_SEPARATOR.$device.DIRECTORY_SEPARATOR.$slug, $variants);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $preset
     */
    protected function generatePack(KeySoundSynthesizer $synthesizer, Filesystem $files, string $device, string $slug, array $preset, string $directory, int $variants): void
    {
        $files->ensureDirectoryExists($directory);

        $manifest = [
            'name' => $preset['name'],
            'description' => $preset['description'],
            'down' => [],
            'up' => [],
        ];

        foreach (['down', 'up'] as $phase) {
            foreach ($synthesizer->categories($device) as $category) {
                for ($variant = 1; $variant <= $variants; $variant++) {
                    $fileName = "{$phase}-{$category}-{$variant}.wav";
                    $seed = crc32("{$device}:{$slug}:{$phase}:{$category}:{$variant}");

                    $files->put($directory.DIRECTORY_SEPARATOR.$fileName, $synthesizer->render($preset, $category, $phase, $seed, $device));
                    $manifest[$phase][$category][] = $fileName;
                }
            }
        }

        $files->put($directory.DIRECTORY_SEPARATOR.'pack.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->components->info("Generated {$preset['name']} ({$device})");
    }
}
