<?php

namespace App\Services;

use Native\Desktop\Facades\Settings;

/**
 * Reads and writes the sound settings shared with the Electron sound engine.
 *
 * Settings live in NativePHP's electron-store under a single key, which the engine
 * watches. The resolved pack paths are stored alongside so the engine never has to
 * know where the Laravel app lives on disk.
 */
class KeySoundSettings
{
    public const STORE_KEY = 'larakey';

    public function __construct(protected SoundPackRepository $packs) {}

    /**
     * @return array{enabled: bool, volume: int, pack: string, key_up: bool, pitch_variation: bool, mouse_enabled: bool, mouse_pack: string, pack_path: string|null, mouse_pack_path: string|null}
     */
    public function all(): array
    {
        $stored = Settings::get(self::STORE_KEY, []);

        $settings = array_merge(config('larakey.defaults'), is_array($stored) ? $stored : []);

        $pack = $this->resolvePack($settings['pack'], SoundPackRepository::KEYBOARD);
        $mousePack = $this->resolvePack($settings['mouse_pack'], SoundPackRepository::MOUSE);

        return [
            'enabled' => (bool) $settings['enabled'],
            'volume' => (int) $settings['volume'],
            'pack' => $pack['slug'] ?? $settings['pack'],
            'key_up' => (bool) $settings['key_up'],
            'pitch_variation' => (bool) $settings['pitch_variation'],
            'mouse_enabled' => (bool) $settings['mouse_enabled'],
            'mouse_pack' => $mousePack['slug'] ?? $settings['mouse_pack'],
            'pack_path' => $pack['path'] ?? null,
            'mouse_pack_path' => $mousePack['path'] ?? null,
        ];
    }

    /**
     * Merge the given values into the stored settings.
     *
     * @param  array{enabled?: bool, volume?: int, pack?: string, key_up?: bool, pitch_variation?: bool, mouse_enabled?: bool, mouse_pack?: string}  $values
     * @return array{enabled: bool, volume: int, pack: string, key_up: bool, pitch_variation: bool, mouse_enabled: bool, mouse_pack: string, pack_path: string|null, mouse_pack_path: string|null}
     */
    public function update(array $values): array
    {
        $settings = array_merge($this->all(), $values);
        $settings['pack_path'] = $this->packs->find($settings['pack'], SoundPackRepository::KEYBOARD)['path'] ?? null;
        $settings['mouse_pack_path'] = $this->packs->find($settings['mouse_pack'], SoundPackRepository::MOUSE)['path'] ?? null;

        Settings::set(self::STORE_KEY, $settings);

        return $settings;
    }

    /**
     * Ensure the engine has a complete settings payload, e.g. on first launch.
     */
    public function sync(): void
    {
        Settings::set(self::STORE_KEY, $this->all());
    }

    /**
     * The stored pack, falling back to the first installed pack when it no longer exists.
     *
     * @return array{slug: string, name: string, description: string, path: string}|null
     */
    protected function resolvePack(string $slug, string $device): ?array
    {
        return $this->packs->find($slug, $device) ?? $this->packs->all($device)->first();
    }

    public function isEnabled(): bool
    {
        return $this->all()['enabled'];
    }

    public function toggle(): bool
    {
        return $this->update(['enabled' => ! $this->isEnabled()])['enabled'];
    }
}
