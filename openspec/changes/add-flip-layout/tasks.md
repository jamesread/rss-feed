# Tasks

Each checkbox is sized to be one beads issue. `Depends on:` lists task IDs that must be closed first; tasks without it can start immediately. Design references (D1–D9) point to design.md.

## 1. Test tooling

- [x] 1.1 Add Vitest as a dev dependency: `npm install -D vitest`, add `"test": "vitest run"` to `package.json`, create `vitest.config.mjs` (`environment: 'node'`, `include: ['tests/js/**/*.test.js']`, no Laravel plugin) and a trivial `tests/js/smoke.test.js`. Verify: `npm test` passes and `npm run build` still succeeds (D9).
- [x] 1.2 Add a GitHub Actions workflow `.github/workflows/tests.yml` (the fork's CI runs on GitHub, so Forgejo workflows never run) with jobs for `npm test` + `npm run build`, PHPUnit and PHPStan on pull requests and branch pushes, and call it from the quay.io publish workflow so images are only pushed after the tests pass. Leave `.forgejo/` untouched for upstream rebases. Verify: actionlint reports no errors, and PHPUnit/PHPStan pass locally. Depends on: 1.1

## 2. Layout module (`resources/js/flip/layout.js`, pure functions)

- [x] 2.1 Implement template parsing: a function that turns a character map (array of strings) into slots `{key, col, row, colSpan, rowSpan, area}` and throws on non-rectangular or missing cells. Verify: Vitest cases for a valid map, an L-shaped slot (throws) and a ragged row (throws). Depends on: 1.1
- [x] 2.2 Add the four template sets from D3 (tablet-portrait, tablet-landscape, phone-portrait, phone-landscape; ≥ 4 each) with their grid sizes from D2. Verify: Vitest checks every template parses, covers its grid exactly, and that adjacent templates in each rotation differ. Depends on: 2.1
- [x] 2.3 Implement `classify(width, height)` per the D2 table. Verify: Vitest cases for iPhone portrait/landscape, iPad mini/Air/Pro portrait/landscape, a 1920 × 1080 desktop, and the boundary values `min(w,h) = 599/600` and `w = h`. Depends on: 2.1
- [x] 2.4 Implement slot size classes (`s|m|l|xl` by share of page area, D3 thresholds). Verify: Vitest asserts each template has at most one `xl` slot and at least two distinct size classes. Depends on: 2.2
- [x] 2.5 Implement page splitting with template rotation `(i + p − 1) mod n` and greedy post consumption (D3, D4). Verify: Vitest checks that all posts appear exactly once, in server order across pages, that consecutive pages use different templates, and that server page 2 starts on a different template than page 1. Depends on: 2.2
- [x] 2.6 Implement thumbnail-first slot assignment within a page (slots by area desc, stable; posts stably partitioned by `thumbnail`). Verify: Vitest with a mixed page puts a thumbnail post in the largest slot and keeps relative order inside each group. Depends on: 2.4, 2.5
- [x] 2.7 Implement the end page (D4): leftover posts plus nav tiles (`hasNext = posts.length === 100`, `hasPrev = page > 1`) in a uniform grid sized from the viewport aspect. Also handle "no end page" when nothing is left and there is no nav, and the empty-posts result. Verify: Vitest cases for r = 0 with/without nav, r = 3 with both nav tiles, 0 posts, and that the uniform grid covers its area with no gaps. Depends on: 2.5
- [x] 2.8 Expose one entry point `buildPages({posts, page, width, height})` returning `{deviceClass, grid, pages[]}` and a helper `findPageForPost(pages, postIndex)`. Verify: Vitest checks determinism (two calls give deep-equal results), anchor lookup after switching from tablet-portrait to tablet-landscape, and that a 100-post input yields 10–20 pages on tablet classes and more on phone classes. Depends on: 2.6, 2.7

## 3. View switching hooks in shared files

- [x] 3.1 Add the "Flip View" button to `resources/js/Parts/FormatSelector.vue` (5th button, inline SVG `bi-columns-gap`, same active styling). Verify: `npm run build` succeeds, the button appears in the browser, and clicking it writes `flip` to `localStorage['post-format']` (DevTools → Application).
- [x] 3.2 Create a stub `resources/js/Parts/FlipView.vue` (fixed full-viewport container that receives `posts, page, feeds, tag, feed, search` and renders a placeholder text plus an empty `#flip-drawer-body`) and wire it into `Posts.vue` per D1: `v-if`/`v-else` on the posts column, `v-show` on the sidebar column, `<Teleport defer … :disabled>` around the inner sidebar block, `block!` binding, `FormatSelector` inside the sidebar block when flip is active. Verify: selecting flip hides the two-column layout, and selecting card again restores the unchanged layout. `php artisan test` passes. Depends on: 3.1

## 4. Pager and tiles

- [x] 4.1 Implement `resources/js/Parts/FlipTile.vue` for thumbnail tiles: absolute `object-cover saturate-50` image (`loading="lazy" decoding="async"`), bottom gradient, feed-name chip `Link` to `f/…` above a stretched title link (`target="_blank"`), relative time on `l`/`xl`, clamped description on `xl` (D6). Verify: in flip view with the stub layout, a tile opens the post in a new tab, the feed chip opens the feed filter, and the text is readable over a white image in both themes. Depends on: 3.2
- [x] 4.2 Add the no-thumbnail placeholder to `FlipTile.vue`: feed-colour background, title sized and line-clamped per size class, full title in `title` (D6). Verify: in the browser, placeholders at `s` and `xl` size show no overflow and show a tooltip. Depends on: 4.1
- [x] 4.3 Render the real mosaic in `FlipView.vue`: measure the container with `ResizeObserver`, call `buildPages`, render each page as a CSS grid (`repeat(cols,1fr)` / `repeat(rows,1fr)`, 2 px gap, slots via grid-column/row spans), with the horizontal scroll-snap pager from D5 (`scroll-snap-stop: always`, `overscroll-behavior-x: contain`, hidden scrollbar). Verify: in responsive emulation at iPad portrait, every page is fully covered by tiles with no gaps, swiping/scrolling snaps one page at a time, and the arrangement is identical after reload. Depends on: 2.8, 4.2
- [x] 4.4 Render the end page (nav tiles as existing `Link :href="pagePath" :data="{page: page ± 1}"`) and the "No posts" panel. Verify: on a server page with 100 posts, the last screen page shows "Next page", which loads `?page=2` in flip view at screen page 1. A search with no matches shows "No posts". Depends on: 4.3
- [x] 4.5 Track the current page from `scroll` (rAF-throttled) and add the "n / total" indicator. Verify: the indicator updates while swiping and is correct after a programmatic jump. Depends on: 4.3
- [ ] 4.6 Re-layout on resize/rotation, keeping the anchor post visible (D5, `findPageForPost`, instant jump after `nextTick`). Verify: in emulation, rotating iPad portrait→landscape while on page 5 lands on the page containing that page's first post. Depends on: 4.5

## 5. Navigation input

- [x] 5.1 Keyboard paging on `window` (→/PageDown/Space, ←/PageUp/Shift+Space, Home/End), ignored for form fields and while the drawer is open, smooth scroll unless `prefers-reduced-motion` (D5). Verify: each key moves one page on desktop. Typing in the search field doesn't page. With reduced motion emulated in DevTools, page changes jump instantly. Depends on: 4.5
- [x] 5.2 Mouse-wheel paging with accumulation, a 50 px threshold and a 200 ms idle lock (D5). Verify: one wheel notch moves exactly one page in Chrome and Safari on macOS. A trackpad two-finger vertical swipe moves one page. Horizontal trackpad scroll still snaps natively. Depends on: 5.1
- [x] 5.3 Prev/next edge buttons shown only for `(hover: hover) and (pointer: fine)`, hidden on the first/last page. Verify: visible and working with a mouse, absent in touch emulation. Depends on: 4.5

## 6. Chrome, drawer, safe areas

- [x] 6.1 Add `viewport-fit=cover` to the viewport meta in `resources/views/app.blade.php` and add `tests/Feature/ViewportMetaTest.php` asserting it is present on `/`. Verify: `php artisan test --filter=ViewportMetaTest` passes.
- [x] 6.2 Add body safe-area padding to `resources/css/app.css` (`@layer base`, D7). Verify: on desktop the card/list/grid/compact views look unchanged (no visual diff at 1440 px). Depends on: 6.1
- [ ] 6.3 Inset the flip pages and all chrome by `env(safe-area-inset-*)` while the background stays edge to edge (D7). Verify: in Safari Responsive Design Mode / Xcode Simulator on iPhone landscape, no tile or control sits under the notch or home indicator. Depends on: 4.3, 6.1
- [x] 6.4 Add the floating menu button with the context label (Posts / #tag / feed name in colour / ?= term, D7). Verify: the label matches the `Posts.vue` heading for home, a tag, a single feed and a search. Depends on: 6.3
- [x] 6.5 Implement the drawer shell in `FlipView.vue` (backdrop, left panel, close button, Escape, outside tap; contains `#flip-drawer-body`). Verify: the teleported sidebar shows Back to home, search, tags, feeds with the hidden-feed marker and the view selector. Escape and a backdrop tap close it on the same screen page. Picking card leaves flip view. Depends on: 3.2, 6.4
- [x] 6.6 Dark/light polish: paired `dark:` classes for chrome, drawer, indicator, buttons and tile loading background (D8). Verify: with `APP_THEME=dark`, `light` and `system` (OS toggled), flip view matches the other views' palette and overlay text stays legible. Depends on: 6.5, 4.2

## 7. Parity and device verification (integration checks)

- [x] 7.1 Parity: filters, search, hidden feeds. Compare flip and list view for home, a tag, a feed, a hidden feed's own page and a search term (posts shown, hidden feed absent on home and present under its filter, search term kept in the field). Verify: a checklist with results is recorded in the beads issue. Depends on: 6.5
- [x] 7.2 Parity: view persistence and pagination. Reload keeps flip, navigating filters keeps flip, leaving flip persists the new view, Next/Previous behave as in list view (including search paging). Verify: checklist recorded. Depends on: 4.4, 6.5
- [ ] 7.3 Parity: feed reload status. With a feed whose cache is outdated, open flip view and wait for reload. Verify: opening the drawer shows "Reloaded, refresh to show changes" and the link works. Depends on: 6.5
- [ ] 7.4 Device check on iPad (real device or Simulator), portrait and landscape: full-bleed mosaic, swipe settles one page per fling, rotation keeps the anchor post, tiles open posts, the drawer works, both themes. Verify: screenshots of both orientations attached to the beads issue. Depends on: 4.6, 5.3, 6.6
- [ ] 7.5 Device check on iPhone (real device or Simulator), portrait and landscape: fewer tiles per page, safe areas respected in flip view and in list view, text fits small tiles. Verify: screenshots attached. Depends on: 6.2, 6.6
- [ ] 7.6 Desktop check (Chrome and Safari or Firefox, 1440 px and 1920 px): keys, wheel, edge buttons and the drawer work, and the other four views are unchanged. Verify: checklist recorded. Depends on: 5.2, 5.3, 6.6
- [x] 7.7 Full test run: `npm test`, `npm run build`, `php artisan test`. Verify: all three pass. Depends on: 2.8, 6.1

## 8. Documentation

- [x] 8.1 Update the README feature line from "4 different post layout modes (card, grid, list, compact)" to include flip, and add `npm test` to the testing section. Verify: the README renders with both changes and `npm test` runs as documented. Depends on: 7.7

## 9. Review follow-ups (after first device test)

- [x] 9.1 Block vertical page scroll in flip view: `flip-scroll-lock` class on `<html>` while `FlipView` is mounted (`overflow: hidden; overscroll-behavior: none` on html/body), `touch-action: pan-x pinch-zoom` on the pager, `overscroll-behavior: contain` on the drawer (D10). Verify: in flip view `html`/`body` compute `overflow: hidden` and the pager `pan-x pinch-zoom`; after switching to list the class is gone and the page scrolls. The iPad portrait swipe itself is confirmed in 7.4.
- [x] 9.2 Keep the drawer open after "« Back": `data-flip-keep-drawer` on the sidebar back link, a click handler on `#flip-drawer-body` storing a timestamp in `sessionStorage`, reopened on the next `FlipView` mount if under 10 s old (D10). Verify: from `/t/coding` with the drawer open, "« Back" lands on `/` with the drawer open and the flag cleared; a tag link still closes the drawer.
- [x] 9.3 Rename the sidebar link "« Back to home" to "« Back" in `Posts.vue` (all views). Verify: the label reads "« Back" in the flip drawer and in list view.

## Workflow follow-up

- Archive the change after the manual checks in section 7 are signed off.
- Optional follow-up change: thumbnail dimensions/resizing (design.md, Open Questions).
