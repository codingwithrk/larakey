/**
 * Global input via uiohook (macOS, Windows and Linux X11).
 *
 * uiohook-napi is imported lazily: on Linux it links against X11 libraries that a
 * Wayland-only system may not have, and a failed import must not take the engine down.
 */
export async function startUiohook(onInput) {
    const { uIOhook, UiohookKey } = await import('uiohook-napi');

    const keyCategories = {
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
    const buttonCategories = {
        1: 'default',
        2: 'right',
        3: 'middle',
    };

    // uiohook repeats keydown while a key is held; only the first press should click.
    const pressedKeys = new Set();

    uIOhook.on('keydown', (event) => {
        if (pressedKeys.has(event.keycode)) {
            return;
        }

        pressedKeys.add(event.keycode);
        onInput({ device: 'keyboard', phase: 'down', category: keyCategories[event.keycode] || 'default' });
    });

    uIOhook.on('keyup', (event) => {
        pressedKeys.delete(event.keycode);
        onInput({ device: 'keyboard', phase: 'up', category: keyCategories[event.keycode] || 'default' });
    });

    uIOhook.on('mousedown', (event) => {
        if (buttonCategories[event.button]) {
            onInput({ device: 'mouse', phase: 'down', category: buttonCategories[event.button] });
        }
    });

    uIOhook.on('mouseup', (event) => {
        if (buttonCategories[event.button]) {
            onInput({ device: 'mouse', phase: 'up', category: buttonCategories[event.button] });
        }
    });

    uIOhook.start();

    return () => uIOhook.stop();
}
