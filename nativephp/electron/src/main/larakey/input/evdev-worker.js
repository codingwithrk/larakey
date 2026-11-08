import fs from 'fs';
import os from 'os';
import { categorizeEvent, eventRecordSize, parseEvent, parseInputDevices } from './evdev-codes.js';

/**
 * Linux input reader, run in an Electron utility process.
 *
 * Reading a /dev/input device blocks a libuv thread until the next event arrives, so it
 * runs here with its own thread pool instead of starving the main process's. Only sound
 * triggers ({ device, phase, category }) are posted back, never key codes or characters.
 */
const RESCAN_INTERVAL_MS = 5000;

const recordSize = eventRecordSize(os.arch());
const streams = new Map();
const openDevices = new Set();
const deniedDevices = new Set();
let lastStatus = '';

function report() {
    const status = { type: 'status', open: openDevices.size, denied: deniedDevices.size };
    const serialized = JSON.stringify(status);

    if (serialized !== lastStatus) {
        lastStatus = serialized;
        process.parentPort.postMessage(status);
    }
}

function watch(device) {
    if (streams.has(device.path) || deniedDevices.has(device.path)) {
        return;
    }

    const stream = fs.createReadStream(device.path, { highWaterMark: recordSize * 64 });
    let pending = Buffer.alloc(0);

    streams.set(device.path, stream);

    stream.on('open', () => {
        openDevices.add(device.path);
        report();
    });

    stream.on('data', (chunk) => {
        pending = pending.length > 0 ? Buffer.concat([pending, chunk]) : chunk;
        let offset = 0;

        for (; offset + recordSize <= pending.length; offset += recordSize) {
            const trigger = categorizeEvent(parseEvent(pending, offset, recordSize));

            if (trigger) {
                process.parentPort.postMessage({ type: 'input', ...trigger });
            }
        }

        pending = pending.subarray(offset);
    });

    stream.on('error', (error) => {
        if (error.code === 'EACCES' || error.code === 'EPERM') {
            deniedDevices.add(device.path);
        }
    });

    stream.on('close', () => {
        streams.delete(device.path);
        openDevices.delete(device.path);
        report();
    });
}

function scan() {
    let devices = [];

    try {
        devices = parseInputDevices(fs.readFileSync('/proc/bus/input/devices', 'utf8'));
    } catch (error) {
        console.error('[larakey] Unable to list input devices', error);
    }

    devices.forEach(watch);
    report();
}

scan();

// Picks up keyboards and mice plugged in after launch. Unplugged devices close their
// stream and are reopened here if they come back.
setInterval(scan, RESCAN_INTERVAL_MS);
