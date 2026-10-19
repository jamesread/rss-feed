# Proposal

## Why

The UI currently follows the OS/browser colour scheme only (Tailwind v4's default `dark:` variant is driven by `prefers-color-scheme`), so there is no way to force light or dark mode for a deployment. Operators of this fork want to pin the theme per instance via configuration, either in `.env` or as an environment variable passed to the Docker container.

## What Changes

- Add a new environment variable `APP_THEME` that accepts exactly one of `system`, `dark` or `light` (single value, so the options are mutually exclusive by construction).
- `system` (the default) keeps today's behaviour: follow `prefers-color-scheme`.
- `dark` forces the dark theme regardless of OS/browser preference; `light` forces the light theme.
- Unknown or empty values fall back to `system` rather than breaking the page.
- The configured theme is rendered server-side onto the root `<html>` element so there is no flash of the wrong theme on load.
- Document `APP_THEME` in the README "App Configuration" section and in `.env.example`.
- No UI toggle, no per-user/session preference, no changes to the existing colour palette.

Fork note: this is a fork of `codeberg.org/danb/rss`. The change is kept small and additive (one config key, one attribute on the layout, one CSS variant definition) so it stays easy to rebase onto upstream and could be offered upstream later.

## Capabilities

### New Capabilities
- `ui-theme`: How the web UI selects its colour scheme (system-following, forced dark, forced light) and how that selection is configured.

### Modified Capabilities
<!-- none: no existing specs in openspec/specs/ -->

## Impact

- `config/app.php`: new `theme` key read from `APP_THEME`.
- `resources/views/app.blade.php`: root element carries the resolved theme.
- `resources/css/app.css`: redefine Tailwind's `dark` variant so it honours the configured theme (existing `dark:` classes in Vue components are unchanged).
- `.env.example`, `README.md`: documentation.
- Docker: no Dockerfile change needed; `docker/run.sh` already runs `config:cache` at container start, so `APP_THEME` passed via `-e`/compose `environment:` is picked up. Changing the value requires a container restart.
- Tests: new feature test asserting the rendered theme attribute for each value and the fallback.
- No new dependencies, no database changes, no breaking changes (default preserves current behaviour).
