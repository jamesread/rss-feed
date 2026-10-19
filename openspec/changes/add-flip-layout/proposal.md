# Proposal

## Why

The four existing views (card, grid, list, compact) are all vertical, column-bound lists built for desktop. On an iPad, the main device for reading this fork, a lot of the screen goes to the sidebar and margins, and scrolling through 100 posts feels like reading a log rather than skimming a magazine. A Flipboard-style view puts thumbnails first, uses the whole screen and lets the reader flip through posts one page at a time.

## What Changes

- New fifth view, **flip**, selectable from the existing view selector next to card / grid / list / compact and persisted in the same `localStorage['post-format']` key.
- In flip view the posts page becomes a full-viewport, horizontally paged mosaic (CSS scroll-snap; touch swipe, keyboard, mouse wheel and on-screen buttons). Each screen page is a grid of tiles in varied sizes. A tile shows the post thumbnail (or the feed-colour placeholder already used by grid view, scaled to the tile) with the title and feed name on top.
- Tile layout uses **several fixed templates per device class** (phone/tablet × portrait/landscape), rotated from page to page so consecutive pages look different. Within a page, posts that have a thumbnail fill the largest slots first. The arrangement depends only on the post list, the server page number and the device class, so it is the same on every reload.
- The sidebar (Back to home, search, tags, feeds incl. reload status) is not removed. In flip view it is shown as an overlay drawer opened from a small floating button, and the view selector is shown in that drawer so the user can leave flip view.
- Server pagination stays unchanged (100 posts per page, `?page=N`). The posts of one server page are split into screen pages. The final screen page links to the previous and next server page using the same rules as the existing views.
- `viewport-fit=cover` is added to the viewport meta tag so iOS reports safe-area insets. The flip view pads its controls and mosaic by those insets. The other views get matching safe-area padding so they don't run under the notch.
- **Vitest** is added as a dev-only test runner for the pure layout module (templates, page splitting, slot assignment, determinism). No runtime JS dependencies are added.

No backend, route, controller or Inertia prop changes. Nothing is **BREAKING**: existing views and their persisted choice behave as before.

## Capabilities

### New Capabilities
- `post-flip-view`: The flip post view: full-screen paged tile mosaic, its layout rules, navigation, device classes and safe areas, and how it keeps parity with the other views (filters, search, hidden feeds, view switching and persistence, pagination, opening posts, sidebar features).

### Modified Capabilities
<!-- None. openspec/specs/ is empty. The in-flight change `fix-format-selector-position` introduces `post-view-format`, which says the selector sits top-right "for every view (card, grid, list, compact)". Flip view moves the selector into the drawer. Since that spec isn't archived yet, the exception is written as a requirement in `post-flip-view` instead of as a delta on `post-view-format`. See design.md, Open Questions. -->

## Impact

- **New files:** `resources/js/Parts/FlipView.vue` (pager, chrome, keyboard/wheel handling), `resources/js/Parts/FlipTile.vue` (one tile), `resources/js/flip/layout.js` (pure layout module + templates), `tests/js/flipLayout.test.js`, `vitest.config.mjs`.
- **Small edits to shared files:**
  - `resources/js/Pages/Posts.vue`: render `FlipView` instead of the posts column when `postFormat === 'flip'`, restyle the existing sidebar as a drawer in that mode, show `FormatSelector` inside it.
  - `resources/js/Parts/FormatSelector.vue`: one extra button.
  - `resources/views/app.blade.php`: `viewport-fit=cover`.
  - `resources/css/app.css`: safe-area padding for the non-flip layout.
  - `package.json`: `vitest` dev dependency + `test` script.
- **Tests:** a new PHP feature test asserting the viewport meta. Vitest unit tests for the layout module. Existing PHPUnit suites are unaffected.
- **Docker build:** `npm ci` installs one extra dev dependency in the build stage. The runtime image is unchanged.
- **Rebasing on upstream:** changes stay in new files plus about 30 changed lines in the five shared files above.
