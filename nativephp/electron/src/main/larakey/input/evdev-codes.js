/**
 * Pure helpers for reading Linux evdev input (/dev/input/event*).
 *
 * Used on Wayland, where desktop compositors don't let apps observe global input. These
 * helpers only decode which *kind* of key or button changed. Characters are never derived.
 */

export const EV_KEY = 0x01;

// EV_REP: real keyboards auto-repeat. Power buttons, lid switches and similar
// devices also use the "kbd" handler but don't repeat, so this filters them out.
const EV_REP_BIT = 1 << 0x14;

// First button code. Everything below it is a keyboard key.
const BTN_MISC = 0x100;

const KEY_CATEGORIES = {
    1: 'modifier', // Esc
    14: 'backspace',
    15: 'modifier', // Tab
    28: 'enter',
    29: 'modifier', // Left Ctrl
    42: 'modifier', // Left Shift
    54: 'modifier', // Right Shift
    56: 'modifier', // Left Alt
    57: 'space',
    58: 'modifier', // Caps Lock
    96: 'enter', // Keypad Enter
    97: 'modifier', // Right Ctrl
    100: 'modifier', // Right Alt
    111: 'backspace', // Delete
    125: 'modifier', // Left Meta
    126: 'modifier', // Right Meta
};

const BUTTON_CATEGORIES = {
    0x110: 'default', // BTN_LEFT
    0x111: 'right', // BTN_RIGHT
    0x112: 'middle', // BTN_MIDDLE
};

const ARCHITECTURES_WITH_64_BIT_TIMEVAL = ['x64', 'arm64', 'ppc64', 's390x', 'riscv64', 'loong64', 'mips64el'];

/**
 * Size in bytes of one `struct input_event`: a timeval (8 or 16 bytes) followed by
 * type (u16), code (u16) and value (s32).
 */
export function eventRecordSize(arch) {
    return ARCHITECTURES_WITH_64_BIT_TIMEVAL.includes(arch) ? 24 : 16;
}

export function parseEvent(buffer, offset, recordSize) {
    const fields = offset + recordSize - 8;

    return {
        type: buffer.readUInt16LE(fields),
        code: buffer.readUInt16LE(fields + 2),
        value: buffer.readInt32LE(fields + 4),
    };
}

/**
 * Turn a raw event into a sound trigger, or null when it shouldn't make a sound.
 */
export function categorizeEvent({ type, code, value }) {
    // 1 = pressed, 0 = released, 2 = auto-repeat (ignored, like a held real key).
    if (type !== EV_KEY || (value !== 0 && value !== 1)) {
        return null;
    }

    const phase = value === 1 ? 'down' : 'up';

    if (code < BTN_MISC) {
        return { device: 'keyboard', phase, category: KEY_CATEGORIES[code] ?? 'default' };
    }

    const category = BUTTON_CATEGORIES[code];

    return category ? { device: 'mouse', phase, category } : null;
}

/**
 * Find keyboards and pointing devices in the contents of /proc/bus/input/devices.
 */
export function parseInputDevices(text) {
    return text
        .split(/\n\s*\n/)
        .map((block) => {
            const name = block.match(/^N: Name="(.*)"$/m)?.[1] ?? '';
            const handlers = (block.match(/^H: Handlers=(.*)$/m)?.[1] ?? '').trim().split(/\s+/);
            const events = parseInt(block.match(/^B: EV=([0-9a-f]+)$/im)?.[1] ?? '0', 16);
            const eventHandler = handlers.find((handler) => /^event\d+$/.test(handler));

            if (!eventHandler) {
                return null;
            }

            const keyboard = handlers.includes('kbd') && (events & (1 << EV_KEY)) !== 0 && (events & EV_REP_BIT) !== 0;
            const pointer = handlers.some((handler) => /^mouse\d+$/.test(handler));

            if (!keyboard && !pointer) {
                return null;
            }

            return { name, path: `/dev/input/${eventHandler}`, keyboard, pointer };
        })
        .filter(Boolean);
}
