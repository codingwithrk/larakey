import { BrowserWindow, ipcMain, systemPreferences, utilityProcess } from 'electron';
import Store from 'electron-store';
import fs from 'fs';
import path from 'path';
import { setAutostart } from './autostart.js';
import { startUiohook } from './input/uiohook.js';
import { playerScript } from './player.js';

/**
 * Larakey sound engine.
 *
 * Listens to system-wide key and mouse button events and forwards them to a hidden,
 * offline audio window. Input comes from uiohook on macOS, Windows and Linux X11, and
 * from the kernel's input devices (evdev) on Linux Wayland, where compositors block
 * global input. Laravel owns the settings UI and writes its choices to the shared
 * electron-store under the "larakey" key; this engine watches that key and reports its
 * input status under "larakey_engine". Only the key category (never the character
 * typed) leaves the input sources.
 */
const SETTINGS_KEY = 'larakey';
const STATUS_KEY = 'larakey_engine';

let player = null;
let playerReady = false;
let stopInput = null;
let inputWorker = null;
let autostartEnabled = null;
const loadedPackPaths = { keyboard: null, mouse: null };
let settingsWatchTimer = null;
let accessibilityPrompted = false;

const store = new Store();
let currentSettings = readSettings();

function readSettings() {
    const settings = store.get(SETTINGS_KEY) || {};

    return {
        enabled: settings.enabled ?? true,
        volume: settings.volume ?? 50,
        keyUp: settings.key_up ?? true,
        pitchVariation: settings.pitch_variation ?? true,
        mouseEnabled: settings.mouse_enabled ?? true,
        packPath: settings.pack_path ?? null,
        mousePackPath: settings.mouse_pack_path ?? null,
        launchAtLogin: settings.launch_at_login ?? false,
    };
}

function readSoundFiles(packPath, sounds = {}) {
    const files = {};

    for (const [category, names] of Object.entries(sounds)) {
        files[category] = names
            .map((name) => path.join(packPath, name))
            .filter((file) => file.startsWith(packPath) && fs.existsSync(file))
            .map((file) => fs.readFileSync(file));
    }

    return files;
}

function loadPack(device, packPath) {
    if (!playerReady || !packPath || packPath === loadedPackPaths[device]) {
        return;
    }

    try {
        const manifest = JSON.parse(fs.readFileSync(path.join(packPath, 'pack.json'), 'utf8'));

        player.webContents.send('larakey:pack', {
            device,
            down: readSoundFiles(packPath, manifest.down),
            up: readSoundFiles(packPath, manifest.up),
        });

        loadedPackPaths[device] = packPath;
    } catch (error) {
        console.error('[larakey] Unable to load sound pack', packPath, error);
    }
}

function applyAutostart() {
    if (process.platform !== 'linux' || autostartEnabled === currentSettings.launchAtLogin) {
        return;
    }

    try {
        setAutostart(currentSettings.launchAtLogin);
        autostartEnabled = currentSettings.launchAtLogin;
    } catch (error) {
        console.error('[larakey] Unable to update autostart entry', error);
    }
}

function applySettings() {
    currentSettings = readSettings();
    applyAutostart();

    if (!playerReady) {
        return;
    }

    player.webContents.send('larakey:settings', currentSettings);
    loadPack('keyboard', currentSettings.packPath);
    loadPack('mouse', currentSettings.mousePackPath);
}

/**
 * Apply settings as soon as Laravel writes them. electron-store's own watcher polls
 * every few seconds on macOS, so we watch the store's directory directly instead
 * (watching the directory survives the atomic rename used when the file is saved).
 */
function watchSettings() {
    const fileName = path.basename(store.path);

    fs.watch(path.dirname(store.path), { persistent: false }, (_, changedFile) => {
        if (changedFile && changedFile !== fileName) {
            return;
        }

        clearTimeout(settingsWatchTimer);
        settingsWatchTimer = setTimeout(() => {
            const previous = JSON.stringify(currentSettings);

            try {
                if (JSON.stringify(readSettings()) !== previous) {
                    applySettings();
                }
            } catch (error) {
                console.error('[larakey] Unable to read settings', error);
            }
        }, 15);
    });
}

function emit({ device, phase, category }) {
    if (!playerReady || !currentSettings.enabled) {
        return;
    }

    if (device === 'mouse' && !currentSettings.mouseEnabled) {
        return;
    }

    player.webContents.send('larakey:key', { device, phase, category });
}

/**
 * Report how input is being captured, so the settings window can explain problems
 * (for example a Linux user who isn't allowed to read /dev/input).
 */
function reportStatus(backend, problem = null) {
    store.set(STATUS_KEY, {
        platform: process.platform,
        session: process.env.XDG_SESSION_TYPE || null,
        backend,
        problem,
    });
}

function createPlayer() {
    player = new BrowserWindow({
        show: false,
        skipTaskbar: true,
        webPreferences: {
            nodeIntegration: true,
            contextIsolation: false,
            backgroundThrottling: false,
            autoplayPolicy: 'no-user-gesture-required',
        },
    });

    player.webContents.on('did-finish-load', () => {
        player.webContents.executeJavaScript(playerScript);
    });

    player.loadURL('data:text/html;charset=utf-8,<!doctype html><title>Larakey</title>');
}

function isWaylandSession() {
    return process.env.XDG_SESSION_TYPE === 'wayland' || Boolean(process.env.WAYLAND_DISPLAY);
}

function startEvdev() {
    inputWorker = utilityProcess.fork(path.join(import.meta.dirname, 'larakey-evdev.js'), [], {
        serviceName: 'Larakey Input',
        // Each open input device holds a thread while it waits for events.
        env: { ...process.env, UV_THREADPOOL_SIZE: '64' },
    });

    inputWorker.on('message', (message) => {
        if (message.type === 'input') {
            emit(message);
        } else if (message.type === 'status') {
            const problem = message.open === 0 ? (message.denied > 0 ? 'input-permission' : 'no-devices') : null;
            reportStatus('evdev', problem);
        }
    });

    inputWorker.on('exit', (code) => {
        inputWorker = null;

        if (code !== 0) {
            reportStatus('evdev', 'input-crashed');
        }
    });

    stopInput = () => inputWorker?.kill();
}

async function startInput() {
    if (stopInput) {
        return;
    }

    // macOS requires Accessibility access before a global key hook can observe input.
    if (process.platform === 'darwin' && !systemPreferences.isTrustedAccessibilityClient(!accessibilityPrompted)) {
        accessibilityPrompted = true;
        reportStatus('uiohook', 'accessibility');
        setTimeout(startInput, 3000);

        return;
    }

    if (process.platform === 'linux' && isWaylandSession()) {
        startEvdev();

        return;
    }

    try {
        stopInput = await startUiohook(emit);
        reportStatus('uiohook');
    } catch (error) {
        console.error('[larakey] Unable to start the global input hook', error);

        if (process.platform === 'linux') {
            startEvdev();
        } else {
            reportStatus('uiohook', 'input-unavailable');
        }
    }
}

/**
 * Must run before the app is ready.
 */
export function configureLarakey(app) {
    if (process.platform === 'linux') {
        // Lets global shortcuts work on Wayland through the desktop's GlobalShortcuts portal.
        app.commandLine.appendSwitch('enable-features', 'GlobalShortcutsPortal');
    }
}

export function startLarakey(app) {
    ipcMain.on('larakey:ready', (event) => {
        if (event.sender !== player?.webContents) {
            return;
        }

        playerReady = true;
        loadedPackPaths.keyboard = null;
        loadedPackPaths.mouse = null;
        applySettings();
    });

    watchSettings();

    applyAutostart();
    createPlayer();
    startInput();

    app.on('will-quit', () => {
        stopInput?.();
    });
}
