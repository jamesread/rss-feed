# Design

## Context

See proposal.md for motivation. Current state, from `add-theme-env-config`:

- `config/app.php` has `'theme' => env('APP_THEME', 'system')`.
- `resources/views/app.blade.php` normalises that value in an `@php` block and renders `data-theme="…"` on `<html>`, which also carries `class="h-full dark:bg-gray-900"`. That class is the dark page background this change makes configurable.
- `resources/css/app.css` defines a custom `dark` variant driven by `data-theme` (forced dark, or `system` plus `prefers-color-scheme: dark`). Every `dark:` utility already follows `APP_THEME`.
- Tailwind v4 defines `--color-gray-900` as `oklch(21% 0.034 264.665)`, which is about `#101828`.
- vlucas/phpdotenv (used by Laravel) parses an **unquoted** `APP_DARK_BACKGROUND=#101828` as an empty string, because `#` starts a comment. Quoted (`"#101828"`) works. Docker `-e` and compose `environment:` pass `#` through literally.

## Goals / Non-Goals

**Goals:**
- The default output stays pixel-identical to today when the variable is unset.
- No user-controlled text reaches the HTML or CSS without strict validation.
- Reuse the existing `dark` variant so the colour follows `APP_THEME` without new media-query logic.

**Non-Goals:**
- No light-theme background setting, and no palette or theming system.
- No support for named colours, `rgb()`, `oklch()` or 8-digit hex with alpha.
- No runtime switching. The value is read from config, so a container restart is needed after changing it, the same as `APP_THEME`.

## Decisions

### 1. Expose the colour as a CSS custom property on `<html>` and use a Tailwind arbitrary value with fallback
In the Blade `@php` block, validate the value. If it is valid, render `style="--app-dark-bg: #rrggbb"` on `<html>`. Replace `dark:bg-gray-900` with `dark:bg-[var(--app-dark-bg,var(--color-gray-900))]`.

- When the variable is unset or invalid, no `style` attribute is rendered. The fallback resolves to the exact `--color-gray-900` token, so the default is byte-identical in effect (not a hex approximation of the oklch value).
- The class still uses the `dark:` variant, so it applies only when the dark theme is active (forced or system-dark) and the light theme is unaffected.
- Alternatives considered:
  - An inline `<style>html[data-theme=dark]{background:…}</style>` block. Rejected because it would duplicate the system/media-query logic that the `dark` variant already owns.
  - Defaulting the config to `'#101828'` and always emitting it. Rejected because the hex value is only an approximation of the oklch token and would shift today's default slightly.
  - Overriding `--color-gray-900` globally. Rejected because it would recolour every `gray-900` use (flip view buttons, search input, light-mode text), not just the page background.

### 2. Config default is `null`, documented default is `#101828`
Use `'dark_background' => env('APP_DARK_BACKGROUND')`, which is null when unset. The docs say the default is the current gray, about `#101828`, so users have a hex value to start from.

### 3. Strict whitelist validation in the view
Normalise the value with `trim`, then `strtolower`, then strip one leading `#`. Accept it only if it matches `^[0-9a-f]{3}([0-9a-f]{3})?$`, then re-prefix `#`. Anything else, including empty strings, gives no colour. The regex allows only hex digits, so no quotes, `;`, `<` or `}` can reach the `style` attribute. Blade's `{{ }}` escaping stays in place as a second layer.

- This sits next to the existing theme normalisation in the same `@php` block, so all layout config handling stays in one place. Alternative: a helper or service class. Rejected because two small normalisations do not justify a new class, and the fork aims to keep changes small for rebasing.

### 4. Accept the value without `#`, and recommend quoting
Because of the dotenv comment behaviour above, an unquoted `#rrggbb` in `.env` arrives empty and falls back to the default silently. The docs and `.env.example` therefore show the quoted form (`APP_DARK_BACKGROUND="#101828"`) and mention that the `#` may be left out (`APP_DARK_BACKGROUND=101828`). For containers, `-e APP_DARK_BACKGROUND=#101828` works as is.

## Risks / Trade-offs

- [An unquoted `#…` in `.env` is silently ignored] → The docs and `.env.example` show the quoted form, and the `#` is optional.
- [Tailwind may not generate the arbitrary class] → Tailwind scans the Blade view (check that `@source` or auto-detection covers `resources/views`). A task checks that the built CSS contains the selector.
- [Users expect the flip view or sidebar to change too] → The README states the scope explicitly.
- [The variable name might clash with a future upstream setting] → It uses the `APP_` prefix and the same style as `APP_THEME`. It is easy to rename while the fork's changes are unmerged.

## Migration Plan

This is additive with no migration. Unset gives today's look. Rollback means removing the variable or reverting the change.

## Open Questions

None.
