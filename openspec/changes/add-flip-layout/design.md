# Design

## Context

See proposal.md (Why) and `specs/post-flip-view/spec.md` for the required behaviour. This section covers only the parts of the current code that shape the approach.

**How views work today**
- There is a single Inertia page, `resources/js/Pages/Posts.vue`. The selected view is the `postFormat` data field (default `'card'`). It is written to `localStorage['post-format']` in `updateFormat()` and read back in `mounted()`, so every page load first renders card and then switches.
- `Posts.vue` renders `FormatSelector` (four buttons, emits `change`) and loops over `posts` with `Parts/Post.vue`, passing `format`. `Post.vue` switches its markup with `formatCard/Grid/List/Compact` flags. Width per view is set by the wrapper `max-w-lg` / `max-w-2xl` / `w-full`.
- The sidebar is the first column of `Posts.vue`: Back to home, search (`submitSearch` → `$inertia.visit(pagePath, {data: {query}})`, fired on `submit` and on input `change`), tags derived from `feeds`, and `Feed.vue` entries. `Feed.vue` polls `feed?url=…` every 3 s while a feed is reloading and then shows "Reloaded, refresh to show changes". Below `md`, the sidebar is collapsed behind a "Toggle filters & options" button (`mobileShowOptions`).
- Links use the custom `Link` wrapper (`Parts/Link.js`), which resolves relative hrefs against `<base href>`. Feed filter URLs are `f/${encodeURIComponent(encodeURIComponent(feed.url))}`, tag URLs are `t/<tag without #>`.

**Data and pagination**
- `PostViewController::renderPostsView` returns `feeds`, `posts` (max 100, newest first), `page`, `search`, `tag`, `feed`. Pages are selected with `?page=N`. Hidden feeds are filtered on the server: home uses `getVisible()`, while tag and feed routes use all matching feeds. The posts in the props are therefore already correct for every filter.
- The frontend shows "Next Page" when `posts.length === 100` and "Previous Page" when `page > 1`, both as `<Link :href="pagePath" :data="{page: …}">`. `pagePath` is `window.location.pathname` only, so these links keep tag and feed filters (they are in the path) but drop the search `query`. Flip view reuses the same links, so its behaviour matches.
- Post JSON fields used by the frontend: `url`, `title`, `description`, `thumbnail` (nullable path under `storage/`), `published_at` (unix seconds), `feed` (`name`, `color`, `url`, `tags`, `hidden`). `id` and `guid` are hidden from serialization.

**Thumbnails**
- `PostThumbnailFetcher` downloads the page's `og:image` as-is (no resizing, rejected if > 1 MB, no width/height stored) to `storage/app/public/thumbs/<feed>/<post>.<ext>`. Views show it via `storage/${post.thumbnail}` with `object-cover saturate-50`.
- The only no-thumbnail placeholder is in grid view: a block in the feed colour with the title in `text-5xl` bold white at 50 % opacity.

**Platform**
- Tailwind 4 with a custom `dark` variant driven by `data-theme` on `<html>` (from `add-theme-env-config`). `dark:` classes work in new components without extra setup.
- The viewport meta is `width=device-width, initial-scale=1` without `viewport-fit=cover`, so iOS reports all `env(safe-area-inset-*)` as 0 and letterboxes the page in landscape.
- Vue 3.5, Inertia 3, Vite 8. No JS test runner. CI (`.forgejo/workflows`) runs PHPUnit and PHPStan only. The Docker build runs `npm ci && npm run build`.

## Goals / Non-Goals

**Goals:**
- Keep all flip logic in new files. Shared files get only the hooks needed to switch to flip view, which keeps the fork easy to rebase.
- Make the layout a pure, framework-free module (posts + server page + device class → screen pages with slots) so determinism can be unit-tested.
- Use native scrolling for paging (scroll-snap) instead of a JS gesture engine.

**Non-Goals:**
- Changing how the other four views look or behave, apart from the safe-area padding that `viewport-fit=cover` requires.
- Fixing existing quirks that flip view inherits: the card-first flash on load, the Previous/Next links not keeping the search query, search firing on input `change`.
- Remembering the screen page across reloads or browser back navigation. Reloading or going back always starts at screen page 1 of that server page.
- Backend changes (thumbnail sizes, page size). See Open Questions.
- 3D page-flip animation, article import, feed management.

## Decisions

### D1. Component structure and how `Posts.vue` hosts flip view

New files:
- `resources/js/flip/layout.js`: templates, device classification, page splitting, slot assignment, end-page layout. Pure functions only, no Vue, no DOM.
- `resources/js/Parts/FlipView.vue`: full-viewport pager. Computes pages from `layout.js`, renders `FlipTile`s and the end page, owns the chrome (menu button + context label, page indicator, prev/next buttons), the drawer shell (backdrop, panel, close button, Escape), keyboard/wheel handling and resize handling.
- `resources/js/Parts/FlipTile.vue`: one tile (thumbnail or placeholder, overlay text, link behaviour) with a `size` prop (`s|m|l|xl`).

Edits to `Posts.vue` (about 15 lines):
1. In the template, `<FlipView v-if="postFormat === 'flip'" …props… />` and `v-else` on the existing posts column.
2. `v-show="postFormat !== 'flip'"` on the outer sidebar column.
3. Wrap the inner sidebar block (`<div class="md:max-w-xs w-full hidden md:block" …>`) in `<Teleport defer to="#flip-drawer-body" :disabled="postFormat !== 'flip'">`, and extend its class binding to `{'block!': mobileShowOptions || postFormat === 'flip'}`.
4. Inside that block, `<FormatSelector v-if="postFormat === 'flip'" …>` at the top, so the user can leave flip view from the drawer.

`FormatSelector.vue` gets a fifth button (`selectFormat('flip')`, title "Flip View", Bootstrap icon `bi-columns-gap`, matching the inline SVG style of the others).

*Why Teleport:* it moves the existing sidebar DOM, including its `Feed.vue` instances, into the drawer without duplicating markup or unmounting anything. Reload polling and the "Reloaded, refresh" link keep working, and the search input keeps its `v-model` (spec: Feed reload status, Sidebar available as a drawer). `defer` (Vue ≥ 3.5) resolves the target after `FlipView` has mounted in the same render.

*Alternatives considered:*
- Extract the sidebar into a `PostsSidebar.vue` used by both layouts. Cleaner, but it moves about 40 lines out of an upstream file, which almost guarantees rebase conflicts.
- Duplicate search/tags/feeds inside `FlipView`. Duplicates logic, and a second set of `Feed.vue` instances would poll twice.
- A separate Inertia page or route for flip. Breaks the single-page view switching and would need backend changes.

`Post.vue` is not touched. Tiles are different enough that a new component is simpler than another format flag.

### D2. Device classes and grid sizes

The class is computed from the pager's own size (`ResizeObserver`), not from the user agent:

| Class | Condition (CSS px, inside safe area) | Grid (cols × rows) | Tiles per page | Typical devices |
|---|---|---|---|---|
| `phone-portrait` | `min(w,h) < 600`, `h ≥ w` | 2 × 4 | 3–5 | iPhone portrait (≈390 × 760) |
| `phone-landscape` | `min(w,h) < 600`, `w > h` | 4 × 2 | 3–4 | iPhone landscape (≈750 × 370) |
| `tablet-portrait` | `min(w,h) ≥ 600`, `h ≥ w` | 4 × 6 | 6–8 | iPad mini/Air/Pro portrait (744–1024 wide) |
| `tablet-landscape` | `min(w,h) ≥ 600`, `w > h` | 6 × 4 | 5–10 | iPad landscape, desktop windows |

These grids give cells close to square on all target devices (iPad Air portrait 820 × 1180 → ~205 × 197 px cells, landscape ~197 × 205, iPhone 15 portrait ~195 × 190). A template written once for a class therefore looks the same on every device in it. Desktop uses `tablet-landscape`, so on a 1920 × 1080 window cells are ~320 × 270. That is acceptable for "works, not optimized".

Rows/columns are CSS grid `repeat(n, 1fr)` with a 2 px gap (magazine-style seams, no outer margin). Each slot is placed by `grid-column`/`grid-row` start/span.

### D3. Page templates (sizing rule: template + content)

Each class has four templates, written as character maps (each letter is one slot; slots must be rectangles and must cover the grid exactly). Starting set:

```
tablet-portrait (4×6)
 TP1 (7)    TP2 (6)    TP3 (8)    TP4 (7)
 AABB       AAAA       AAAB       AABC
 AABB       AAAA       AAAB       AABC
 CCDE       AAAA       AAAC       DDDD
 CCDE       BBCC       DDEC       DDDD
 FFGG       BBCC       FFEG       EFGG
 FFGG       DDEF       FFHG       EFGG

tablet-landscape (6×4)
 TL1 (7)    TL2 (8)    TL3 (5)    TL4 (10)
 AAABBC     AABBCC     AAAABB     AABCDD
 AAABBC     AABBCC     AAAABB     AAEEDD
 AAADEE     DEEFGG     AAAACC     FGEEHI
 FFGDEE     DEEHGG     AAAADE     FGJJHI

phone-portrait (2×4)       phone-landscape (4×2)
 PP1 (3) PP2 (5) PP3 (4) PP4 (3)   PL1 (4) PL2 (4) PL3 (3) PL4 (4)
 AA      AA      AA      AB        AABC    ABBC    AAAB    ABCC
 AA      BC      AA      AB        AADC    ABBD    AAAC    DBCC
 BC      BD      BB      CC
 BC      EE      CD      CC
```

The exact maps may be tuned during implementation. The unit tests (D9) enforce the rules, not the pictures: every slot is a rectangle, the grid is covered with no gaps or overlaps, every class has at least three templates, and adjacent templates in the rotation differ.

**Slot size class** (drives typography and which extras a tile shows): computed from the slot's share of the page area. `xl` ≥ 30 % (one hero per page at most), `l` ≥ 15 %, `m` ≥ 7 %, `s` below that. (Lower thresholds would give phone pages, which have only 8 cells, several `xl` tiles.) Thresholds live in `layout.js` next to the templates.

**Template rotation:** screen page `i` (0-based) of server page `p` uses `templates[(i + p − 1) mod n]`. Consecutive pages always differ (n ≥ 2), and server page 2 doesn't start with the same template as page 1. The result depends only on `(class, p, i)`.

**Content-driven placement inside a page:**
1. Page splitting is greedy in server order. Page `i` takes the next `slots(template_i)` posts, so posts never move between pages because of thumbnails (spec: Page order preserved).
2. Within a page, slots are sorted by area descending, with ties broken by template order (top-left first). Posts are stably partitioned into "has thumbnail" first, then "no thumbnail", and assigned to slots in that order.

*Alternatives considered:*
- A pure template with no reordering. A text-only post could land in the hero slot, which looks empty. Rejected.
- Pulling thumbnail posts forward across pages to fill hero slots. Breaks newest-first order. Rejected.
- A dynamic bin-packing layout. Hard to keep gap-free and stable, and untestable as pictures. Rejected.

### D4. Last page and how server pagination maps to screen pages

- One server page (≤ 100 posts) is one horizontal pager. Changing server page is an Inertia visit, as today. `FlipView` is re-mounted and starts at screen page 1.
- **End page:** when the remaining posts `r` are fewer than the next template's slot count, they go on a final *end page* instead of a template page. If `posts.length === 100` there is a "Next page" tile, and if `page > 1` a "Previous page" tile. Both are the existing `<Link :href="pagePath" :data="{page: page ± 1}">`, so parity holds, including the existing quirk that the search query is dropped (see Context).
- End page layout is a uniform grid built by `layout.js` for `n = r + navTiles` tiles: `cols = clamp(round(sqrt(n · W/H)), 1, n)`, `rows = ceil(n / cols)`, and the last tile in the last row spans the leftover columns. If `r = 0` and there is no nav tile, there is no end page. If `r = 0` with nav, the end page holds only the nav tile(s).
- With no posts at all, `FlipView` renders a single centered "No posts" panel. The chrome and drawer button stay (spec: Empty result).

With ~7 tiles per tablet page, 100 posts give about 14–15 screen pages. With ~4 per phone page, about 25.

### D5. Paging mechanics (horizontal scroll-snap)

- Pager: `position: fixed; inset: 0` with `height: 100dvh`, a horizontal flex row of pages with `overflow-x: auto; scroll-snap-type: x mandatory; overscroll-behavior-x: contain`, and the scrollbar hidden. Each page has `flex: 0 0 100%; scroll-snap-align: start; scroll-snap-stop: always`. `scroll-snap-stop: always` gives "exactly one page per swipe" (spec: Fast fling).
- **Current page:** read on `scroll` (throttled with `requestAnimationFrame`) as `round(scrollLeft / clientWidth)`.
- **Programmatic paging** (keys, buttons, wheel): `scrollTo({left: idx · clientWidth, behavior})`, where `behavior` is `'auto'` if `matchMedia('(prefers-reduced-motion: reduce)')` matches and `'smooth'` otherwise.
- **Keyboard:** a `keydown` listener on `window` while mounted. → / PageDown / Space go forward, ← / PageUp / Shift+Space go back, Home and End jump to the first and last page. It ignores events whose target is an `input`, `textarea`, `select` or contentEditable, and it is inactive while the drawer is open (Escape closes the drawer instead).
- **Mouse wheel:** a `wheel` listener (`passive: false`) handles mainly vertical deltas (`|deltaY| > |deltaX|`). It accumulates `deltaY` (normalising `deltaMode` lines to ×16 px), pages once when the total passes 50 px, then ignores further wheel events until 200 ms of wheel silence. One notch or one trackpad gesture moves one page. Horizontal trackpad gestures are left to native scrolling.
- **Prev/next buttons:** semi-transparent circular buttons at the left and right edges, vertically centred, shown only under `@media (hover: hover) and (pointer: fine)`. They are hidden on the first and last page.
- **Tap vs swipe:** browsers do not fire `click` when a touch turns into a scroll, so tiles are plain links with no extra gesture code (spec: Swipe does not open).
- **Resize / rotation:** the `ResizeObserver` recomputes the class. If the class changed, the pages are recomputed. In both cases, after `nextTick`, the view jumps (instantly) to the page that contains the *anchor post*, which is the first post of the page that was current. The anchor is identified by `post.url` plus its index in `posts` (index alone is enough within one server page).

*Alternatives considered:*
- A JS swipe/carousel library such as Swiper (~40 kB gzip) or Embla (~7 kB). Both give rubber-banding and page events, but native scroll-snap already gives momentum and snapping on iOS Safari 15+ and every evergreen desktop browser at 0 kB. The proposal's "no heavy dependency" constraint decides it.
- Translating a track with pointer events by hand. More code, worse scroll physics.
- Vertical snap. Not Flipboard-like (user choice).

### D6. Tile rendering

- The tile is a `div` with `position: relative; overflow: hidden`, and its background is `bg-gray-100 dark:bg-gray-800` while the image loads.
- **With thumbnail:** `<img :src="`storage/${post.thumbnail}`" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover saturate-50">`. `saturate-50` matches the muted look of the other views (an assumption; it's one class to change). A bottom gradient (`bg-linear-to-t from-black/80 via-black/40 to-transparent`) sits under white text, so titles are legible over bright images in both themes.
- **Without thumbnail:** feed-colour background (`:style="{backgroundColor: post.feed.color}"`) with the title in bold white at 60 % opacity, as in grid view. Font size follows the slot class (`xl` `text-4xl`, `l` `text-2xl`, `m` `text-lg`, `s` `text-base`) with `line-clamp` (6/5/4/3), so text never overflows. `title` carries the full title.
- **Overlay text by size:** feed name (feed colour on a dark chip, so it stays readable over the gradient) and title on all sizes. Relative time ("About 3 hours ago", via the existing `timestampToRelativeTime`, ISO time in `title`) on `l`/`xl`. A two-line-clamped description on `xl` only. Tags are not shown on tiles, since they are reachable from the drawer.
- **Links:** the title is an `<a :href="post.url" target="_blank">` whose `::after` covers the whole tile, so the entire tile is clickable without nesting links. The feed name is a `Link` to `f/…`, positioned above it (`relative z-10`). Hover (pointer devices only) slightly brightens the image.

### D7. Chrome, drawer and safe areas

- **Viewport:** `app.blade.php` meta becomes `width=device-width, initial-scale=1, viewport-fit=cover`.
- **Flip view:** the pager background goes edge-to-edge. Each page's grid is inset by `padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left)`, so tiles never sit under the notch or home indicator. On devices without insets, all four are 0, which gives the full-bleed mosaic.
- **Chrome** (all offset by the same insets):
  - Top-left: a menu button plus the context label. The label is "Posts", "#tag", the feed name in its colour (when `feed && feeds.length === 1`, as in `Posts.vue`), and "?= term" when searching.
  - Bottom centre: the "3 / 14" indicator.
  - Left and right edges: the prev/next buttons.
  - Style: small, semi-opaque pills (`bg-white/80 dark:bg-gray-900/80 backdrop-blur`), so they work over any tile in both themes.
- **Drawer** (owned by `FlipView`): a backdrop (`bg-black/40`) and a left panel `w-[min(22rem,85vw)]`, full height, padded by the insets, `bg-gray-50 dark:bg-gray-800` (the sidebar colours). It contains a close button and `<div id="flip-drawer-body">`, the Teleport target from D1. It closes on the close button, a backdrop click and Escape, and it is re-created closed after any Inertia visit because the page component is re-mounted.
- **Other views:** `app.css` adds `body { padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left); }` in `@layer base`. Preflight's `border-box` keeps `h-full` correct. On desktop this does nothing.

*Alternative considered:* toggle `viewport-fit=cover` from JS only while flip is mounted, which would leave the other views untouched. iOS Safari doesn't reliably re-evaluate the viewport meta after load, which would make safe areas flaky exactly on the target device. Rejected in favour of a static meta tag plus one CSS rule.

### D8. Theme

All new colours use paired `dark:` classes, which the `data-theme` variant resolves for `system`, `dark` and `light`. Overlay text sits on a fixed dark gradient, so its legibility doesn't depend on the theme.

### D9. Tests

- **Vitest** (dev dependency, `"test": "vitest run"`) with its own `vitest.config.mjs` (`environment: 'node'`, `include: ['tests/js/**/*.test.js']`). That keeps it from loading the Laravel Vite plugin and leaves `vite.config.mjs` untouched. `tests/js/flipLayout.test.js` covers:
  - template validity per class (rectangles, exact cover)
  - classification thresholds
  - rotation (adjacent pages differ, depends on `p`)
  - greedy split keeps order and loses no posts
  - thumbnail-first assignment
  - end-page rules (r = 0 with/without nav, partial page, empty posts)
  - determinism (same input gives deep-equal output)
  - size-class thresholds
  - anchor lookup after a class change
- *Why Vitest:* it is Vite-native (no Babel/Jest config) and dev-only, and it adds nothing to the runtime bundle or the Docker runtime image. *Alternative:* `node --test` with zero dependencies. Viable for pure functions, but it lacks Vitest's watch mode and ESM/Vite resolution, and Vitest scales to component tests if needed later.
- **PHPUnit:** a new `tests/Feature/ViewportMetaTest.php` asserts the home HTML contains `viewport-fit=cover`. Inertia props are unchanged, so `PostViewControllerTest` already covers server parity (filters, hidden feeds, search, pagination).
- **CI:** an optional new workflow file `.forgejo/workflows/vitest.yml` (`npm ci && npm test`). It's a new file, so there's no rebase conflict.
- **Manual:** device checks per tasks.md section 7.

### Existing mechanics not in the requested parity list

| Mechanic in current code | Flip view handling |
|---|---|
| Format read from `localStorage` in `mounted()` (card flash on load) | Inherited unchanged: flip appears after the first render, like the other views. |
| Feed reload polling + "Reloaded, refresh to show changes" (`Feed.vue`) | Sidebar is teleported, not re-created, so polling continues and the link appears in the drawer. |
| Mobile "Toggle filters & options" button | Hidden in flip view (the drawer replaces it); restored when leaving flip. |
| "« Back to home" link (when filtered or page ≠ 1) | Part of the sidebar block, shown in the drawer. |
| Posts heading/breadcrumb (Posts / #tag / feed in colour / ?= search) | Context label in the flip chrome (D7). |
| Tag chips on each post | Not shown on tiles; tags are in the drawer. |
| Relative published time with ISO tooltip | Shown on `l`/`xl` tiles. |
| Post description | Shown on `xl` tiles, line-clamped. |
| Hover highlight on posts | Image brighten on hover, pointer devices only. |
| Next-page heuristic `posts.length === 100`; Previous/Next links drop `query` | Same links and rule, so same quirk. |
| Search fires on input `change` as well as submit | Unchanged: closing the iPad keyboard after typing triggers the search. |
| Thumbnails shown `saturate-50` | Kept for visual consistency. |
| Viewing a page triggers reload of outdated feeds (server) | Unchanged; same controller. |
| No message for empty results | Flip shows "No posts" (a full-screen blank would look broken). |

## Risks / Trade-offs

- **Small og:images in hero slots look blurry.** No dimensions are stored, so placement can't avoid them. → Mitigation: `object-cover` and the muted saturation hide it partly. The proper fix is in Open Questions.
- **iOS edge-swipe "back" gesture** on the leftmost page can trigger browser back. → `overscroll-behavior-x: contain` stops scroll chaining, but the system edge gesture can't be blocked. Accepted. It does what users expect from Safari.
- **Body safe-area padding changes the other views on notched iPhones in landscape** (the sidebar background no longer reaches the notch edge). → It's the only change to them, and it's covered by manual task 7.5.
- **Several `scroll-snap` behaviour differences** (Safari < 15 ignores `scroll-snap-stop`). → Target devices run current iPadOS/iOS. On old Safari a fling may skip a page, which is acceptable.
- **The end page looks plainer than template pages.** → It's one page per server page. The uniform grid keeps it gap-free.
- **Position is not remembered** across reloads or back navigation. → Documented non-goal. Opening posts uses new tabs, so the normal reading flow keeps the position.
- **Rebase conflicts in `Posts.vue`.** → The edits are about 15 template lines in two places (the posts column and the sidebar wrapper) plus one import/registration in the script. All logic is in new files.
- **Rollback:** a user who chose flip and then runs a build without it has `'flip'` in `localStorage`. `Post.vue` falls back to its default markup (the `format` prop default doesn't match any flag) until another view is picked. Acceptable. The rollback is a plain revert with no data migration.

## Migration Plan

Frontend-only plus one meta tag. Deploy with the normal image build. No database or config changes. Roll back by reverting the change. The `viewport-fit` meta and body padding revert together.

## Open Questions

- **Thumbnail resolution for large tiles (backend, not in this change).** Stored thumbnails are original `og:image` files, typically ~1200 × 630, which is enough for the largest tablet tile (~800 × 600 CSS px, slightly upscaled at 2× DPR). Some feeds publish small images (logos, ≤ 300 px), and the 1 MB cap drops some large ones. Options for a follow-up change:
  1. Store `thumbnail_width`/`thumbnail_height` when fetching (`getimagesizefromstring`, a migration, added to the post JSON), so `layout.js` can rank posts with large images first for `l`/`xl` slots. The placement step is already a separate function, so this would extend D3 without restructuring.
  2. Generate one resized variant (e.g. 1600 px wide WebP) at fetch time to normalise size and weight.
  3. Raise or make configurable the 1 MB download cap.

  To decide after using flip view on real feeds for a while.
- **OpenSpec ordering with `fix-format-selector-position`.** Its `post-view-format` requirement lists the four existing views. Flip moves the selector into the drawer, which this change specifies in `post-flip-view`. Archiving either change first is fine. A later cleanup could fold the selector-in-drawer rule into `post-view-format`.
