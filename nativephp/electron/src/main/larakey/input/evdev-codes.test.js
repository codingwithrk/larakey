import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { categorizeEvent, EV_KEY, eventRecordSize, parseEvent, parseInputDevices } from './evdev-codes.js';

const PROC_DEVICES = `I: Bus=0019 Vendor=0000 Product=0001 Version=0000
N: Name="Power Button"
P: Phys=LNXPWRBN/button/input0
S: Sysfs=/devices/LNXSYSTM:00/LNXPWRBN:00/input/input2
U: Uniq=
H: Handlers=kbd event2
B: PROP=0
B: EV=3
B: KEY=10000000000000 0

I: Bus=0011 Vendor=0001 Product=0001 Version=ab83
N: Name="AT Translated Set 2 keyboard"
P: Phys=isa0060/serio0/input0
S: Sysfs=/devices/platform/i8042/serio0/input/input3
U: Uniq=
H: Handlers=sysrq kbd leds event3
B: PROP=0
B: EV=120013
B: KEY=402000000 3803078f800d001 feffffdfffefffff fffffffffffffffe

I: Bus=0003 Vendor=046d Product=c52b Version=0111
N: Name="Logitech USB Receiver Mouse"
P: Phys=usb-0000:00:14.0-2/input2:1
H: Handlers=mouse0 event5
B: PROP=0
B: EV=17
B: KEY=ffff0000 0 0 0 0

I: Bus=0018 Vendor=06cb Product=7e7e Version=0100
N: Name="SYNA7DB5:01 06CB:7E7E Touchpad"
H: Handlers=mouse1 event7
B: PROP=5
B: EV=1b

I: Bus=0003 Vendor=0bda Product=58c2 Version=0001
N: Name="Integrated Camera: Integrated C"
H: Handlers=kbd event9
B: PROP=0
B: EV=3
`;

describe('parseInputDevices', () => {
    it('finds keyboards and pointing devices but skips buttons and cameras', () => {
        assert.deepEqual(parseInputDevices(PROC_DEVICES), [
            { name: 'AT Translated Set 2 keyboard', path: '/dev/input/event3', keyboard: true, pointer: false },
            { name: 'Logitech USB Receiver Mouse', path: '/dev/input/event5', keyboard: false, pointer: true },
            { name: 'SYNA7DB5:01 06CB:7E7E Touchpad', path: '/dev/input/event7', keyboard: false, pointer: true },
        ]);
    });

    it('returns nothing for empty input', () => {
        assert.deepEqual(parseInputDevices(''), []);
    });
});

describe('categorizeEvent', () => {
    it('categorizes special keys and falls back to default', () => {
        assert.deepEqual(categorizeEvent({ type: EV_KEY, code: 57, value: 1 }), {
            device: 'keyboard',
            phase: 'down',
            category: 'space',
        });
        assert.deepEqual(categorizeEvent({ type: EV_KEY, code: 28, value: 0 }), {
            device: 'keyboard',
            phase: 'up',
            category: 'enter',
        });
        assert.equal(categorizeEvent({ type: EV_KEY, code: 30, value: 1 }).category, 'default'); // A
        assert.equal(categorizeEvent({ type: EV_KEY, code: 111, value: 1 }).category, 'backspace'); // Delete
        assert.equal(categorizeEvent({ type: EV_KEY, code: 125, value: 1 }).category, 'modifier'); // Meta
    });

    it('categorizes mouse buttons', () => {
        assert.deepEqual(categorizeEvent({ type: EV_KEY, code: 0x110, value: 1 }), {
            device: 'mouse',
            phase: 'down',
            category: 'default',
        });
        assert.equal(categorizeEvent({ type: EV_KEY, code: 0x111, value: 0 }).category, 'right');
        assert.equal(categorizeEvent({ type: EV_KEY, code: 0x112, value: 1 }).category, 'middle');
    });

    it('ignores auto-repeat, other event types and non-click buttons', () => {
        assert.equal(categorizeEvent({ type: EV_KEY, code: 30, value: 2 }), null); // held key repeat
        assert.equal(categorizeEvent({ type: 0x02, code: 0, value: 5 }), null); // EV_REL movement
        assert.equal(categorizeEvent({ type: 0x00, code: 0, value: 0 }), null); // EV_SYN
        assert.equal(categorizeEvent({ type: EV_KEY, code: 0x14a, value: 1 }), null); // BTN_TOUCH
    });
});

describe('parseEvent', () => {
    it('reads type, code and value after the timestamp on 64-bit systems', () => {
        const size = eventRecordSize('x64');
        const buffer = Buffer.alloc(size * 2);
        buffer.writeUInt16LE(EV_KEY, size + 16);
        buffer.writeUInt16LE(57, size + 18);
        buffer.writeInt32LE(1, size + 20);

        assert.equal(size, 24);
        assert.deepEqual(parseEvent(buffer, size, size), { type: EV_KEY, code: 57, value: 1 });
    });

    it('uses 16-byte records on 32-bit systems', () => {
        assert.equal(eventRecordSize('arm'), 16);
        assert.equal(eventRecordSize('ia32'), 16);
    });
});
