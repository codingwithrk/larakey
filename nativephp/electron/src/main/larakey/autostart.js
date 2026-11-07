import fs from 'fs';
import os from 'os';
import path from 'path';

/**
 * Launch at login on Linux via an XDG autostart entry.
 *
 * Electron's app.setLoginItemSettings() only supports macOS and Windows, so on Linux the
 * engine writes ~/.config/autostart/larakey.desktop itself.
 */
export function autostartFile(env = process.env) {
    const configHome = env.XDG_CONFIG_HOME || path.join(os.homedir(), '.config');

    return path.join(configHome, 'autostart', 'larakey.desktop');
}

/**
 * Quote an executable path for a Desktop Entry Exec key: reserved characters are
 * backslash-escaped inside double quotes, then backslashes are escaped again because
 * Exec is also a string value.
 */
export function quoteExec(executable) {
    const quoted = `"${executable.replace(/(["`$\\])/g, '\\$1')}"`;

    return quoted.replace(/\\/g, '\\\\');
}

export function desktopEntry(executable) {
    return [
        '[Desktop Entry]',
        'Type=Application',
        'Name=Larakey',
        'Comment=Mechanical keyboard and mouse sounds',
        `Exec=${quoteExec(executable)}`,
        'Terminal=false',
        'X-GNOME-Autostart-enabled=true',
        '',
    ].join('\n');
}

/**
 * The command that relaunches this app. AppImages run from a temporary mount, so their
 * stable path comes from the APPIMAGE variable instead of process.execPath.
 */
export function launchCommand(env = process.env) {
    return env.APPIMAGE || process.execPath;
}

export function setAutostart(enabled, file = autostartFile(), executable = launchCommand()) {
    if (!enabled) {
        fs.rmSync(file, { force: true });

        return;
    }

    const contents = desktopEntry(executable);

    if (fs.existsSync(file) && fs.readFileSync(file, 'utf8') === contents) {
        return;
    }

    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);
}
