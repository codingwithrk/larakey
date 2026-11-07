# Contributing to Larakey

Thanks for helping make Larakey better! Bug reports, sound pack recipes, fixes and features are
all welcome.

## Ways to contribute

- **Report a bug.** Open an issue with your OS and version, your Larakey version, steps to
  reproduce, and what you expected to happen.
- **Suggest a feature.** Open an issue describing the problem it solves before writing code, so
  we can agree on the approach.
- **Tune or add a sound pack.** See [Sound packs](#sound-packs) below.
- **Send a pull request.** Fixes, features, docs and tests are all welcome.

For security issues, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Development setup

Requirements: PHP 8.3+, Composer, and Node.js 22+ with npm.

```bash
git clone https://github.com/codingwithrk/larakey.git
cd larakey
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
composer native:dev
```

On macOS, grant Accessibility access to the development Electron app when prompted. Without it,
the app can't hear keys and clicks outside its own window.

## Project layout

| Area | Location |
|---|---|
| Settings window, tray menu, global shortcut | `app/`, `resources/views/`, `routes/web.php` |
| Settings shared with the sound engine | `app/Services/KeySoundSettings.php` |
| Sound synthesizer and pack generator | `app/Audio/`, `app/Console/Commands/GenerateSoundPacks.php` |
| Pack recipes and defaults | `config/larakey.php` |
| Sound engine: global key/mouse hook and playback | `nativephp/electron/src/main/larakey/` |
| Tests | `tests/` |

> **Note:** `nativephp/electron` is NativePHP's published Electron project. Running
> `composer update` republishes it and overwrites Larakey's sound engine. Keep engine changes
> inside `src/main/larakey/` and check `git diff` after updating dependencies.

## Coding standards

### PHP

- Follow the existing structure and naming. Check sibling files before adding new ones.
- Use `php artisan make:*` to create classes, tests and other files.
- Use typed parameters and return types, and PHPDoc array shapes where helpful.
- Format your code before committing:

  ```bash
  vendor/bin/pint
  ```

### JavaScript (Electron engine)

- Keep the engine small and fast. It runs on every keypress.
- **Never** send typed characters out of `engine.js`. Only key categories may reach the audio
  player. Pull requests that capture, log or transmit typed text will not be accepted.
- Format with Prettier:

  ```bash
  cd nativephp/electron && npx prettier --write src/main/larakey
  ```

## Tests

Every change should come with tests, and all tests must pass:

```bash
php artisan test --compact
node --test 'nativephp/electron/src/main/larakey/**/*.test.js'
```

- Use Pest feature tests for HTTP, settings and event behavior, and unit tests for pure logic
  such as the synthesizer.
- Mock NativePHP facades (`Settings`, `App`, `MenuBar`) or use `Http::fake()`. Tests must not
  need the desktop app running.
- Bind `App\Support\Platform` (for example `new Platform('Linux')`) when behavior differs by
  operating system. CI runs on Linux, so never rely on the machine's own platform.
- Sound engine logic that doesn't need Electron (input decoding, autostart) is tested with
  Node's built-in test runner in `*.test.js` files next to the code.

## Sound packs

Sounds are synthesized from recipes in `config/larakey.php`, under `presets.keyboard` or
`presets.mouse`. To add or tune a pack:

1. Add or edit a recipe (the config file documents each field).
2. Regenerate the packs:

   ```bash
   php artisan larakey:generate-sounds
   ```

3. Listen to it in the app's **Try it** box, then commit the recipe **and** the regenerated files
   in `public/sounds/`.

Please keep packs to **mechanical** keyboard switches and mouse switches.

**Recorded packs:** only submit recordings you made yourself, or recordings with a license that
allows redistribution under MIT. State the source in your pull request. Don't name a pack after
a brand or product in a way that suggests it's official.

## Pull requests

1. Fork the repository and create a branch from `main`, for example `fix/shortcut-on-windows`.
2. Keep pull requests focused: one fix or feature each.
3. Make sure `vendor/bin/pint` and `php artisan test --compact` both pass. The **Tests** workflow
   checks both on every pull request.
4. Describe **what** changed and **why**, and how you tested it. Say which OS you tested on.
5. For UI changes, include a screenshot of the Settings window in light and dark mode.

Don't change `NATIVEPHP_APP_VERSION` in your pull request. Maintainers handle versioning and
releases (see "Releasing" in the README).

## License

By contributing, you agree that your contributions are licensed under the [MIT License](LICENSE).
