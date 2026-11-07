import { defineConfig, externalizeDepsPlugin } from 'electron-vite';
import { join } from 'path';

export default defineConfig({
    main: {
        build: {
            rollupOptions: {
                input: {
                    index: join(import.meta.dirname, 'src', 'main', 'index.js'),
                    // Larakey's Linux input reader runs as a separate utility process.
                    'larakey-evdev': join(import.meta.dirname, 'src', 'main', 'larakey', 'input', 'evdev-worker.js'),
                },
                plugins: [
                    {
                        name: 'watch-external',
                        buildStart() {
                            this.addWatchFile(
                                join(process.env.APP_PATH, 'app', 'Providers', 'NativeAppServiceProvider.php'),
                            );
                        },
                    },
                ],
            },
        },
        plugins: [externalizeDepsPlugin()],
    },
});
