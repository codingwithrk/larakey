# Security Policy

Larakey listens to system-wide keyboard and mouse events, so we take security and privacy
seriously.

## Supported versions

Only the **latest release** receives security fixes. Please update before reporting an issue.

| Version | Supported |
|---|---|
| Latest release | ✅ |
| Older releases | ❌ |

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report vulnerabilities privately through GitHub:

1. Go to the repository's **Security** tab.
2. Click **Report a vulnerability**.
3. Describe the issue, the steps to reproduce it, the affected version and platform, and the
   possible impact.

You can expect:

- an acknowledgement within **7 days**
- an assessment, and a fix or mitigation plan, as soon as possible after that
- credit in the release notes, if you'd like it

Please give us a reasonable amount of time to release a fix before disclosing the issue publicly.

## What Larakey does with your input

Knowing this helps you judge what counts as a vulnerability:

- The sound engine (`nativephp/electron/src/main/larakey/`) receives raw key and mouse events
  from the operating system. It reduces each one to a **category** (for example `space`,
  `enter`, `modifier` or `left` click) and passes only that category to the audio player.
- On Linux with Wayland, events come from the kernel's input devices (`/dev/input`), read by a
  separate utility process (`input/evdev-worker.js`). That process also sends only categories
  to the engine. Reading these devices requires the user to be in the `input` group, which
  Larakey asks the user to set up themselves and never does on its own.
- **Characters are never** stored, logged, sent to the Laravel app or sent over the network.
- Settings are stored locally in NativePHP's settings store. Larakey makes no network requests
  except update checks against this repository's GitHub Releases.

Any way to recover typed text, bypass these limits or make Larakey send data elsewhere is a
security issue. Please report it.

### In scope

- Leaking keystrokes, typed text or mouse activity
- Code execution through sound packs (`pack.json` or audio files), settings or the update
  mechanism
- Bypassing NativePHP's local API protection or reaching the app's internal endpoints from a
  website
- Vulnerabilities in the GitHub Actions release workflow that could tamper with published builds

### Out of scope

- macOS and Windows warnings caused by **unsigned builds**. This is expected; see the README.
- Issues that require an attacker who already has full control of your user account
- Vulnerabilities in third-party dependencies that are already publicly known. Please report
  them upstream, although we appreciate a heads-up.

## Staying safe

- Download Larakey **only** from this repository's
  [Releases page](../../releases). Builds are unsigned, so a copy from anywhere else could have
  been modified.
- Only install sound packs from sources you trust.
