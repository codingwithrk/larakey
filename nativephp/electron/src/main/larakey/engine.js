import { BrowserWindow, ipcMain, systemPreferences } from 'electron';
import Store from 'electron-store';
import fs from 'fs';
import path from 'path';
import { uIOhook, UiohookKey } from 'uiohook-napi';
import { playerScript } from './player.js';

/**
 * Larakey sound engine.
 *
 * Listens to system-wide key and mouse button events with uiohook and forwards them to a hidden,
 * offline audio window. Laravel owns the settings UI and writes its choices to the
 * shared electron-store under the "larakey" key; this engine watches that key.
 * Only the key category (never the character typed) leaves this module.
 */
const SETTINGS_KEY = 'larakey';

const CATEGORIES = {
    [UiohookKey.Space]: 'space',
    [UiohookKey.Enter]: 'enter',
    [UiohookKey.NumpadEnter]: 'enter',
    [UiohookKey.Backspace]: 'backspace',
    [UiohookKey.Delete]: 'backspace',
    [UiohookKey.Shift]: 'modifier',
    [UiohookKey.ShiftRight]: 'modifier',
    [UiohookKey.Ctrl]: 'modifier',
    [UiohookKey.CtrlRight]: 'modifier',
    [UiohookKey.Alt]: 'modifier',
    [UiohookKey.AltRight]: 'modifier',
    [UiohookKey.Meta]: 'modifier',
    [UiohookKey.MetaRight]: 'modifier',
    [UiohookKey.CapsLock]: 'modifier',
    [UiohookKey.Tab]: 'modifier',
    [UiohookKey.Escape]: 'modifier',
};

// uiohook mouse button numbers.
const MOUSE_CATEGORIES = {
    1: 'default',
    2: 'right',
    3: 'middle',
};

let player = null;
let playerReady = false;
let hookStarted = false;
const loadedPackPaths = { keyboard: null, mouse: null };
let settingsWatchTimer = null;
let accessibilityPrompted = false;
const pressedKeys = new Set();

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

function applySettings() {
    currentSettings = readSettings();

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

function emit(device, phase, category) {
    if (!playerReady || !currentSettings.enabled) {
        return;
    }

    if (device === 'mouse' && !currentSettings.mouseEnabled) {
        return;
    }

    player.webContents.send('larakey:key', { device, phase, category });
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

function startHook() {
    if (hookStarted) {
        return;
    }

    // macOS requires Accessibility access before a global key hook can observe input.
    if (process.platform === 'darwin' && !systemPreferences.isTrustedAccessibilityClient(!accessibilityPrompted)) {
        accessibilityPrompted = true;
        setTimeout(startHook, 3000);

        return;
    }

    uIOhook.on('keydown', (event) => {
        if (pressedKeys.has(event.keycode)) {
            return;
        }

        pressedKeys.add(event.keycode);
        emit('keyboard', 'down', CATEGORIES[event.keycode] || 'default');
    });

    uIOhook.on('keyup', (event) => {
        pressedKeys.delete(event.keycode);
        emit('keyboard', 'up', CATEGORIES[event.keycode] || 'default');
    });

    uIOhook.on('mousedown', (event) => {
        if (MOUSE_CATEGORIES[event.button]) {
            emit('mouse', 'down', MOUSE_CATEGORIES[event.button]);
        }
    });

    uIOhook.on('mouseup', (event) => {
        if (MOUSE_CATEGORIES[event.button]) {
            emit('mouse', 'up', MOUSE_CATEGORIES[event.button]);
        }
    });

    uIOhook.start();
    hookStarted = true;
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

    createPlayer();
    startHook();

    app.on('will-quit', () => {
        if (hookStarted) {
            uIOhook.stop();
        }
    });
}
