import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, describe, it } from 'node:test';
import { autostartFile, desktopEntry, launchCommand, quoteExec, setAutostart } from './autostart.js';

const temporaryDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'larakey-autostart-'));

afterEach(() => {
    fs.rmSync(temporaryDirectory, { recursive: true, force: true });
});

describe('autostartFile', () => {
    it('respects XDG_CONFIG_HOME', () => {
        assert.equal(autostartFile({ XDG_CONFIG_HOME: '/custom' }), '/custom/autostart/larakey.desktop');
    });

    it('defaults to ~/.config', () => {
        assert.equal(autostartFile({}), path.join(os.homedir(), '.config', 'autostart', 'larakey.desktop'));
    });
});

describe('quoteExec', () => {
    it('quotes plain paths and paths with spaces', () => {
        assert.equal(quoteExec('/opt/Larakey/larakey'), '"/opt/Larakey/larakey"');
        assert.equal(quoteExec('/home/me/My Apps/Larakey.AppImage'), '"/home/me/My Apps/Larakey.AppImage"');
    });

    it('escapes reserved characters per the Desktop Entry spec', () => {
        assert.equal(quoteExec('/apps/$weird"name'), String.raw`"/apps/\\$weird\\"name"`);
    });
});

describe('launchCommand', () => {
    it('prefers the AppImage path', () => {
        assert.equal(launchCommand({ APPIMAGE: '/home/me/Larakey.AppImage' }), '/home/me/Larakey.AppImage');
    });

    it('falls back to the running executable', () => {
        assert.equal(launchCommand({}), process.execPath);
    });
});

describe('setAutostart', () => {
    it('writes the entry when enabled and removes it when disabled', () => {
        const file = path.join(temporaryDirectory, 'autostart', 'larakey.desktop');

        setAutostart(true, file, '/opt/Larakey/larakey');
        assert.equal(fs.readFileSync(file, 'utf8'), desktopEntry('/opt/Larakey/larakey'));
        assert.match(fs.readFileSync(file, 'utf8'), /^Exec="\/opt\/Larakey\/larakey"$/m);

        setAutostart(false, file);
        assert.equal(fs.existsSync(file), false);
    });

    it('does nothing when disabling an entry that does not exist', () => {
        assert.doesNotThrow(() => setAutostart(false, path.join(temporaryDirectory, 'missing.desktop')));
    });
});
