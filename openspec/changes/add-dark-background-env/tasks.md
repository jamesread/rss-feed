# Tasks

## 1. Server-side config and rendering

- [x] 1.1 Add a `dark_background` key to `config/app.php` reading `env('APP_DARK_BACKGROUND')` (no default, so null when unset), with a doc-comment block in the same style as the `theme` key; verify `php artisan tinker --execute="var_dump(config('app.dark_background'));"` prints `NULL` with no env set
- [x] 1.2 In the `@php` block of `resources/views/app.blade.php`, normalise the value (trim, lowercase, strip one leading `#`, whitelist `^[0-9a-f]{3}([0-9a-f]{3})?$`, re-prefix `#`). When it is valid, render `style="--app-dark-bg: {{ $darkBackground }}"` on `<html>`, and render no `style` attribute otherwise. Replace `dark:bg-gray-900` with `dark:bg-[var(--app-dark-bg,var(--color-gray-900))]`. Verify by loading `/` with and without the variable set and checking the page source
- [x] 1.3 Add `tests/Feature/DarkBackgroundConfigTest.php` (same setup as `ThemeConfigTest`) with a data provider that sets `config(['app.dark_background' => …])`, requests `/`, asserts 200, and checks the raw HTML. Valid cases must render `--app-dark-bg: <expected>`: `#000000`→`#000000`, `000000`→`#000000`, `#FFF`→`#fff`, `' 123ABC '`→`#123abc`. Fallback cases must not render `--app-dark-bg`: null, `''`, `blue`, `#12345`, `#gggggg`, `rgb(0,0,0)`, `#000;color:red`, `#000000ff`. Also assert the `<html>` class contains `dark:bg-[var(--app-dark-bg,var(--color-gray-900))]` in every case. Verify `php artisan test --filter=DarkBackgroundConfigTest` passes and `ThemeConfigTest` still passes

## 2. Client-side styling

- [x] 2.1 Run `npm run build` and verify the built CSS contains a rule for the new arbitrary class under both the `[data-theme=dark]` selector and the `[data-theme=system]` + `prefers-color-scheme: dark` selector. If Tailwind does not pick up the Blade view, add an `@source` for `resources/views` in `resources/css/app.css` and rebuild
- [x] 2.2 Check in a browser (`npm run dev` + `php artisan serve`, devtools colour-scheme emulation) that: with no variable set, the dark background looks identical to before (computed `background-color` equals the `--color-gray-900` value); with `APP_DARK_BACKGROUND="#000000"` and `APP_THEME=dark`, the page background is black while the sidebar, cards and flip view keep their colours; with `APP_THEME=system`, it is black only under dark emulation; with `APP_THEME=light`, the page is unchanged; there is no flash of the default colour on reload

## 3. Documentation

- [x] 3.1 Add `APP_DARK_BACKGROUND` to the README "App Configuration" block, directly after `APP_THEME`. Cover: hex `#rgb`/`#rrggbb`; the `#` is optional; quote the value in `.env` because an unquoted `#` starts a comment; it only affects the dark theme's main page background, not the sidebar, cards or flip view; invalid values are ignored; restart the container after changing it; the default is the current gray, about `#101828`. Also add a short bullet to the fork-features list near the top. Verify the README example matches the implemented behaviour
- [x] 3.2 Add a commented example (`# APP_DARK_BACKGROUND="#101828"`) to `.env.example` under `APP_THEME`; verify `cp .env.example .env` still yields the default look

## 4. Integration checks

- [x] 4.1 Run `php artisan test` and `vendor/bin/phpstan analyse --memory-limit=1G` and verify both pass
- [x] 4.2 Build and run a test container with podman on a free port under a name other than `rss-flip`/8081, started with `-e APP_THEME=dark -e APP_DARK_BACKGROUND=#000000`. Verify the served `<html>` contains `--app-dark-bg: #000000` and the page renders black on a light-OS browser. Then remove the test container

## Workflow follow-up

- Archive `add-theme-env-config` before archiving this change, because this change adds requirements to the `ui-theme` capability that change introduces.
