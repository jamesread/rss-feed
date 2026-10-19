# Tasks

## 1. Server-side theme configuration

- [x] 1.1 Add a `theme` key to `config/app.php` reading `env('APP_THEME', 'system')`, with a doc-comment block in the same style as neighbouring keys; verify `php artisan tinker --execute="echo config('app.theme');"` prints `system` with no env set
- [x] 1.2 In `resources/views/app.blade.php`, resolve the theme (trim + lowercase; whitelist `system|dark|light`; anything else → `system`) and render it as `data-theme="…"` on `<html>`; verify by loading `/` and seeing the attribute in the page source
- [x] 1.3 Add `tests/Feature/ThemeConfigTest.php` that sets `config(['app.theme' => …])`, requests `/`, and asserts `data-theme="…"` in the raw HTML for: `dark`, `light`, `system`, unset (null) → `system`, `Dark` → `dark`, ` light ` → `light`, `blue` → `system`, `dark,light` → `system`, `''` → `system`; each request returns 200; verify `php artisan test --filter=ThemeConfigTest` passes

## 2. Client-side dark variant

- [x] 2.1 In `resources/css/app.css`, add the `@custom-variant dark { … }` block from design.md (forced `[data-theme=dark]`, plus `[data-theme=system]` under `prefers-color-scheme: dark`); verify `npm run build` succeeds and the built CSS contains both `[data-theme=dark]` and `[data-theme=system]` selectors for a `dark:` utility
- [x] 2.2 Add `color-scheme` base rules per `data-theme` value (dark / light / `light dark`); verify in the built CSS
- [x] 2.3 Manually verify in a browser (`npm run dev` + `php artisan serve`) with OS/devtools emulation in both light and dark preference: `system` follows the preference, `dark` is always dark, `light` is always light, including the `<html>` background and the search input; no flash of the wrong theme on reload

## 3. Documentation

- [x] 3.1 Add `APP_THEME` with comments (allowed values `system|dark|light`, default `system`, invalid → `system`, restart/`config:cache` needed after change) to the README "App Configuration" block; verify the README renders and the example matches the implemented values
- [x] 3.2 Add `APP_THEME=system` to `.env.example` (and optionally `docker/.env.container`); verify `cp .env.example .env` yields a working default

## 4. Integration checks

- [x] 4.1 Run the full suite (`php artisan test`) and static analysis (`vendor/bin/phpstan analyse`) and verify both pass
- [x] 4.2 Build the image (`docker compose -f docker-compose.testing.yml up --build`) with `APP_THEME=dark` added to the `environment:` list, verify the served page has `data-theme="dark"` and renders dark on a light-OS browser; then revert the compose-file edit
