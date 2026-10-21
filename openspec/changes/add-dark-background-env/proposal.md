# Proposal

## Why

The dark theme's page background is fixed to Tailwind's `gray-900`. Operators running this fork on a wall screen, kiosk or next to other dashboards want to match the background to their surroundings (for example pure black on an OLED panel) without rebuilding the frontend. `APP_THEME` already lets them choose the theme per instance; the background colour should be just as easy to set in `.env` or as a container environment variable.

## What Changes

- Add an environment variable `APP_DARK_BACKGROUND` that takes a hex colour (`#rgb` or `#rrggbb`, the leading `#` optional) and sets the page background of the dark theme.
- When unset, empty or invalid, the dark page background stays exactly as it is today (`gray-900`, about `#101828`). Invalid values never break the page.
- It applies wherever the dark theme is active: with `APP_THEME=dark`, and with `APP_THEME=system` when the browser prefers dark. With `APP_THEME=light`, or `system` on a light browser, it has no visible effect.
- Scope is the main page background only, the `<html>` background behind the list, card, grid and compact views. The sidebar, cards, tiles, buttons and the flip view keep their current colours.
- The value is rendered server-side, so there is no flash of the default colour on load.
- Document `APP_DARK_BACKGROUND` in the README "App Configuration" section and in `.env.example`, including the advice to quote the value in `.env` files.

Fork note: like `APP_THEME`, this is one config key plus one change in the layout, so it stays easy to rebase onto upstream `codeberg.org/danb/rss`.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `ui-theme`: adds a requirement for configuring the dark theme's page background. The `ui-theme` capability is introduced by the not-yet-archived change `add-theme-env-config`; this change adds requirements to it and should be archived after that one.

## Impact

- `config/app.php`: new `dark_background` key read from `APP_DARK_BACKGROUND`.
- `resources/views/app.blade.php`: validate the value and expose it to CSS as a custom property on `<html>`; the `dark:bg-gray-900` class on `<html>` falls back to `gray-900` when no valid colour is set.
- `.env.example`, `README.md`: documentation.
- Docker: no image change. `docker/run.sh` already runs `config:cache` at container start, so a value passed with `-e` or compose `environment:` is used after a container restart.
- Tests: extend the feature tests to assert the rendered value for valid, missing and invalid inputs.
- No new dependencies, no database changes, no breaking changes.
