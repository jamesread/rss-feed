# Design

## Context

- Styling uses Tailwind CSS v4 (`resources/css/app.css` is just `@import 'tailwindcss'` plus a few base rules). In v4 the `dark:` variant defaults to `@media (prefers-color-scheme: dark)`, which is why the UI currently only follows the OS.
- `dark:` utilities are used throughout the Vue components (`resources/js/Pages/Posts.vue`, `resources/js/Parts/*.vue`) and on the root `<html>` in `resources/views/app.blade.php` (`dark:bg-gray-900`).
- App settings live in `config/app.php` as `env('APP_…')` keys (e.g. `feed_update_frequency`, `load_post_thumbnails`) and are documented in the README "App Configuration" section.
- The Docker image copies `docker/.env.container` to `/app/.env` at build time; `docker/run.sh` runs `php85 artisan config:cache` on every container start, so container environment variables override `.env` and are baked into the config cache at startup.
- This is a fork of upstream `codeberg.org/danb/rss`; minimal, additive diffs ease future rebases.

## Goals / Non-Goals

**Goals:**
- One config key drives the theme for all pages, server-rendered so there is no theme flash.
- Zero changes to existing `dark:` class usage in components.

**Non-Goals:**
- Runtime/user-facing theme toggle, cookies, localStorage or per-session preference.
- Changing any colours.
- Hot-reloading the value without a restart/`config:cache` rerun.

## Decisions

### 1. Config key `app.theme` from `APP_THEME`, normalised in PHP
Add `'theme' => env('APP_THEME', 'system')` to `config/app.php`, following the existing key style. Normalise (trim, lowercase, whitelist `system|dark|light`, else `system`) at the point of use in the Blade layout via a small inline expression, or a tiny helper if the expression gets unwieldy.
- Why not normalise inside `config/app.php`? Possible, but keeping config files as plain `env()` reads matches the rest of the file. Either is acceptable; the implementer may move normalisation into config if it reads cleaner — spec behaviour is the same.
- Why not a PHP enum class? Overkill for one three-value string in a small app (simplicity rule).

### 2. Render `data-theme="<value>"` on `<html>`
`resources/views/app.blade.php` adds `data-theme="{{ $theme }}"` to the root element. It's server-rendered, so the theme is correct on first paint, and Inertia page navigations never replace `<html>`, so it persists.
- Alternative: add a `dark` class only when forced dark. Rejected: it can't express "forced light on a dark OS" without a second marker, so an attribute with three values is cleaner.
- Alternative: share the theme as an Inertia prop and toggle in JS. Rejected: causes a flash before hydration and adds JS for no benefit.

### 3. Redefine Tailwind's `dark` variant with `@custom-variant`
In `resources/css/app.css`, after the import:

```css
@custom-variant dark {
  &:where([data-theme=dark], [data-theme=dark] *) { @slot; }
  @media (prefers-color-scheme: dark) {
    &:where([data-theme=system], [data-theme=system] *) { @slot; }
  }
}
```

- `dark` → always applies; `system` → applies only under the dark media query (current behaviour); `light` → never applies.
- Selector includes the element itself, so `dark:bg-gray-900` on `<html>` keeps working.
- `:where()` keeps specificity identical to the stock variant, so no cascade surprises.
- Alternative: plain `@custom-variant dark (&:where(.dark, .dark *))` plus a JS `matchMedia` listener for `system`. Rejected: needs JS and flashes.

### 4. Set `color-scheme` to match
Add base rules so native UI (scrollbars, the search `<input>`) matches: `[data-theme=dark] { color-scheme: dark }`, `[data-theme=light] { color-scheme: light }`, `[data-theme=system] { color-scheme: light dark }`. Small and directly tied to the requested behaviour, since otherwise forced dark on a light OS would show light scrollbars.

### 5. No Docker file changes
`run.sh` already caches config at start, so `-e APP_THEME=dark` / compose `environment:` works as is. Optionally add `APP_THEME=system` to `docker/.env.container` for discoverability; not required since the default is `system`.

## Risks / Trade-offs

- [Upstream later changes the dark-mode mechanism] → Change is isolated to one CSS block and one attribute; rebasing conflicts would be small and obvious.
- [Users forget to restart the container after changing `APP_THEME`] → Note in README that changes need a restart (config is cached at startup).
- [Silent fallback hides typos] → Accepted for robustness (spec requires no error page). Could log a warning later if needed.
- [Tailwind `@custom-variant` block syntax requires v4] → Project is already on `tailwindcss ^4.1.18`.

## Migration Plan

Purely additive; the default `system` reproduces current behaviour, so existing deployments are unchanged. Rollback = revert the commit and rebuild the image.
