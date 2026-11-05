/**
 * Renderer-side audio player for the hidden Larakey sound window.
 *
 * This source is injected into a local, offline-only page. It is kept as a plain string
 * so the bundler never rewrites it. Audio buffers are decoded once and played through
 * Web Audio, which keeps latency between a keypress and its sound as low as possible.
 */
export const playerScript = String.raw`
    const { ipcRenderer } = require('electron');

    const context = new AudioContext({ latencyHint: 'interactive' });
    const gain = context.createGain();
    gain.connect(context.destination);

    const buffers = {
        keyboard: { down: {}, up: {} },
        mouse: { down: {}, up: {} },
    };
    let settings = { volume: 50, keyUp: true, pitchVariation: true };

    function pick(list) {
        return list[Math.floor(Math.random() * list.length)];
    }

    async function decode(files) {
        const decoded = {};

        for (const [category, sounds] of Object.entries(files)) {
            decoded[category] = await Promise.all(
                sounds.map((bytes) =>
                    context.decodeAudioData(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength)),
                ),
            );
        }

        return decoded;
    }

    ipcRenderer.on('larakey:pack', async (_, pack) => {
        buffers[pack.device] = { down: await decode(pack.down), up: await decode(pack.up) };
    });

    ipcRenderer.on('larakey:settings', (_, next) => {
        settings = next;
        gain.gain.value = Math.max(0, Math.min(100, settings.volume)) / 100;
    });

    ipcRenderer.on('larakey:key', (_, { device, phase, category }) => {
        if (device === 'keyboard' && phase === 'up' && !settings.keyUp) {
            return;
        }

        const set = buffers[device]?.[phase] ?? {};
        const list = set[category]?.length ? set[category] : set.default;

        if (!list?.length) {
            return;
        }

        if (context.state === 'suspended') {
            context.resume();
        }

        const source = context.createBufferSource();
        source.buffer = pick(list);

        if (settings.pitchVariation) {
            source.playbackRate.value = 0.95 + Math.random() * 0.1;
        }

        source.connect(gain);
        source.start();
    });

    ipcRenderer.send('larakey:ready');
`;
