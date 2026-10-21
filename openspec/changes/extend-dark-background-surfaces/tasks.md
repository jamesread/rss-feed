# Tasks

## 1. Palette tokens and layout

- [x] 1.1 In `resources/css/app.css`, add the `@theme inline` block (`--color-dark-page|surface|raised|line|deep`), the `:root` defaults (Tailwind gray 900/800/700/600/950) and the `:root[data-dark-bg]` derived values from design.md. Verify `npm run build` succeeds and the built CSS contains the `:root[data-dark-bg]` rule with the `color-mix` values, plus `--color-gray-600` and `--color-gray-950` definitions
- [x] 1.2 In `resources/views/app.blade.php`, render `data-dark-bg` on `<html>` only when the value is valid (next to the existing `style`), and replace the `<html>` class `dark:bg-[var(--app-dark-bg,var(--color-gray-900))]` with `dark:bg-dark-page`. Verify the page source of `/` with and without the variable
- [x] 1.3 Update `tests/Feature/DarkBackgroundConfigTest.php`: the class constant becomes `dark:bg-dark-page`. Valid cases also assert `data-dark-bg`, and fallback cases assert that neither `data-dark-bg` nor `--app-dark-bg:` is present. Verify `php artisan test --filter='DarkBackgroundConfigTest|ThemeConfigTest'` passes

## 2. Component class swaps

- [x] 2.1 Main views: in `Posts.vue`, `Post.vue` and `FormatSelector.vue`, swap the dark grays for role tokens per the design.md table: sidebar → `dark-surface`, search input → `dark-page`, `<hr>` and active since filter and mobile toggle hover → `dark-raised`, filter and mobile toggle borders/divides → `dark-line`, post hover bg → `dark-surface`, post hover border → `dark-raised`, active format button → `dark-surface`, FormatSelector border/divide → `dark-line`. Verify `npm run build` succeeds and `npm test` passes
- [x] 2.2 Flip view: in `FlipView.vue` and `FlipTile.vue`, swap: page `dark:bg-gray-950` → `dark-deep`; drawer and page-number buttons → `dark-surface`; close/number hover and tile fallback → `dark-raised`; tile frame → `dark-surface`; edge, nav and progress buttons `dark:bg-gray-900/70|80|85` and `dark:hover:bg-gray-900` → `dark-page` with the same opacity; toggle track → `dark-line`; toggle knob `dark:after:bg-gray-900` → `dark-page`. Verify `npm run build` succeeds, `npm test` passes, and the built CSS contains the `/85` class with an opacity mix of `var(--color-dark-page)`
- [x] 2.3 Check for leftovers: `grep -rnE "dark:(hover:|after:|peer-checked:)?(bg|border|divide)-gray-(600|700|800|900|950)" resources/js resources/views` should return nothing. Any remaining hit must be justified in the task notes (for example the intentionally kept `dark:peer-checked:bg-gray-300` does not match the pattern)

## 3. Documentation

- [x] 3.1 Rewrite the README `APP_DARK_BACKGROUND` entry under App Configuration. Cover: it sets the dark theme's background colour; the sidebar, post hover, controls and flip view use lighter or darker shades of it automatically; pick a dark colour so the light text stays readable; keep the existing format, quoting, invalid-value, restart and default notes. Update the fork-features bullet at the top to match. Verify the README no longer says the sidebar, posts or flip view keep their colours

## 4. Integration checks

- [x] 4.1 Run `php artisan test`, `vendor/bin/phpstan analyse --memory-limit=1G` and `npm test`, and verify all pass
- [x] 4.2 Run a headless browser check (Playwright Chromium from the local cache against `php artisan serve` with a scratch SQLite DB and seeded posts, or a podman test container not named `rss-flip`/`rss-local` and not on 8081/8082). Check: (a) unset + dark: the computed colours of html, sidebar, post hover bg and border, active format button and flip page match the pre-change Tailwind grays; (b) `#006b6b` + dark: html is `#006b6b`, the sidebar and post hover are lighter teal, the hover border is lighter than the sidebar, the flip page is darker teal, and no slate gray is visible in screenshots of the list with a hovered post and of the flip view with the drawer open; (c) `#006b6b` + light theme: unchanged from unset. Clean up servers and containers afterwards

## Workflow follow-up

- Archive `add-theme-env-config`, then `add-dark-background-env`, then this change, because each builds on the `ui-theme` requirements of the previous one.
- After deploying, re-check https://news.local.michard.cc/ hover and sidebar.
