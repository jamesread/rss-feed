# Proposal

## Why

The view selector (card / grid / list / compact) in the top-right of the posts area moves sideways every time a different view is picked. That makes it harder to click through the views, because the button you want next has moved out from under the cursor.

## What Changes

- The view selector stays at the same position, the top-right corner of the posts column, whichever view is selected.
- The "Posts" heading/breadcrumb stays top-left in the same row, also independent of the selected view.
- Post content keeps its current per-view width limits (card narrow, list medium, grid/compact full width); only the header row stops following them.
- No sticky/fixed-on-scroll behaviour: the header row still scrolls away with the list as it does today.
- No changes to the selector's buttons, icons, or the `localStorage` persistence of the chosen view.

## Capabilities

### New Capabilities
- `post-view-format`: How the posts page lets the user pick a view (card, grid, list, compact) and how the view selector is laid out.

### Modified Capabilities
<!-- none: no existing specs in openspec/specs/ -->

## Impact

- `resources/js/Pages/Posts.vue` only (template layout of the posts column header).
- No backend, config, dependency, or API changes. Kept small as a fork-local change so it rebases cleanly onto upstream.
- Independent of `add-theme-env-config`; the two can be applied in either order.
