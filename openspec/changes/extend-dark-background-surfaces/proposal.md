# Proposal

## Why

`APP_DARK_BACKGROUND` (change `add-dark-background-env`) only recolours the `<html>` page background. On a live instance set to `#006b6b`, the page is teal but a hovered post still turns the default slate (`gray-800` with a `gray-700` border). The sidebar, search box, active filter and format buttons, and the whole flip view also stay slate. The result looks unfinished: the configured colour shows up only in the gaps between default-coloured surfaces.

## What Changes

- With `APP_DARK_BACKGROUND` set, every dark-theme surface follows the configured colour, using shades derived from it automatically. No new environment variables are added.
  - **Page:** the configured colour itself (unchanged from the previous change).
  - **Surface** (one step lighter, like `gray-800` today): the sidebar, post hover background, active format-selector button, flip menu drawer and flip tile placeholder.
  - **Raised** (two steps lighter, like `gray-700`): post hover border, sidebar dividers, the active "since" filter, the mobile filter toggle hover, the flip drawer close-button hover, the flip page-number buttons and flip tiles without a feed colour.
  - **Line** (like `gray-600`): the outlines of the filter and format-selector groups, and the flip auto-advance toggle track.
  - **Deep** (one step darker, like `gray-950`): the flip view page background.
  - **Page colour reused:** the search input, the flip edge, nav and progress buttons (translucent page colour), and the flip toggle knob.
- When `APP_DARK_BACKGROUND` is unset or invalid, every one of these surfaces keeps exactly the Tailwind gray it uses today.
- Text colours, the light theme, black divider lines (`dark:border-black`), feed colours and thumbnails are unchanged.
- **BREAKING (fork-internal only):** this reverses the earlier requirement that the sidebar, cards and flip view keep their colours when `APP_DARK_BACKGROUND` is set. Instances that already set the variable will see those areas change colour after the update.
- README: rewrite the `APP_DARK_BACKGROUND` entry and the fork-features bullet to describe the full scope (all dark surfaces including flip view, derived shades, choose a dark colour so the light text stays readable).

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `ui-theme`: the dark background requirement grows from page-only to all dark surfaces with derived shades, including the flip view. That requirement and the `ui-theme` capability come from the not-yet-archived changes `add-theme-env-config` and `add-dark-background-env`. This change must be archived after both.

## Impact

- `resources/css/app.css`: new dark palette tokens (page, surface, raised, line, deep). They default to the current Tailwind grays and are derived from `--app-dark-bg` when a colour is configured. The tokens are exposed to Tailwind as theme colours.
- `resources/views/app.blade.php`: a marker attribute on `<html>` when a valid colour is set, and the `<html>` class switched to the new page token.
- Class swaps in `resources/js/Pages/Posts.vue`, `resources/js/Parts/Post.vue`, `resources/js/Parts/FormatSelector.vue`, `resources/js/Parts/FlipView.vue` and `resources/js/Parts/FlipTile.vue` (dark-variant classes only).
- `tests/Feature/DarkBackgroundConfigTest.php`: assertions for the marker attribute and the updated class.
- `README.md`: updated documentation.
- No new dependencies, env vars, database or Docker changes.
