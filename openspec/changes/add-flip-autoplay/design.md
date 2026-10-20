# Design

## Context

- `FlipView.vue` holds `current` and `pages` (from `buildPages()`), and moves with `goTo(index, instant)`. Swipes update `current` in `onScroll`. Wheel and keyboard input call `goTo`. The drawer state is `drawerOpen`.
- The layout choice is kept in `localStorage` (`post-format`) and read in `Posts.vue` `mounted()`.
- Inertia v3 is used: `router.reload({only: [...]})` re-runs the current URL and replaces only the listed props.
- Target setup: a headless kiosk PC, typically `chromium --kiosk <url>` on boot, with a fresh or locked-down profile and long uptimes.

## Goals / Non-Goals

**Goals:** hands-off cycling through the current view, with data that stays fresh for days, and that is easy to start from a URL.

**Non-Goals:** cycling across server pages or across tags; hiding the mouse cursor; screen wake-lock; a full-screen API button (the kiosk browser handles those).

## Decisions

1. **Pure helper `flip/autoplay.js`:** `parseAutoplayParam(search) → seconds | null`, and a small state object `{enabled, intervalMs, pausedUntil, startedAt}` with `shouldAdvance(now)` / `progress(now)`. This keeps the timing logic unit-testable without DOM timers.
2. **One `setInterval` tick (250 ms)** in `FlipView` drives both the progress bar and the advance. That is cheaper and more robust than chained timeouts. Pausing is `pausedUntil = now + 60 s` on `touchstart`, `wheel`, `keydown` and pointer drag. Scroll events caused by `goTo` itself are ignored with a flag. `document.hidden` and `drawerOpen` count as paused.
3. **Wrap reload:** on advancing from the last page, call `router.reload({only: ['posts', 'feeds', 'hasMore']})` (`hasMore` only if `collapse-duplicate-posts` landed), then `goTo(0, true)` once it resolves. On error, `goTo(0)` with the old data. Page-view side effects stay as they are: `reloadOutdatedFeeds()` runs on each reload, which keeps feeds fresh even without other visitors.
4. **Activation:** `Posts.vue` checks `autoplay` in `window.location.search`. If present, it sets `postFormat = 'flip'` for this page view without overwriting the stored layout, and passes `autoplaySeconds` to `FlipView`. The drawer toggle stores `flip-autoplay` = `'1'`/`'0'` in `localStorage` (try/catch). Enabled = URL param present OR stored `'1'`. Turning the toggle off with a URL param active sets a session flag that disables it until navigation.
5. **Progress bar:** `absolute bottom-0 inset-x-0 h-0.5` inside the safe-area padding (`bottom: env(safe-area-inset-bottom)`), width bound to `progress * 100%`, `bg-gray-900/40 dark:bg-white/40`. It stays below the page indicator pill, which sits 0.5 rem higher.
6. **Respect reduced motion:** with `prefers-reduced-motion: reduce`, page turns use `goTo(index, true)` (instant). Auto-advance itself still runs.

## Risks / Trade-offs

- [Memory growth over days of `router.reload`.] → Only props are swapped; the component tree stays mounted. Covered by a long-running browser check (task 4.2).
- [A kiosk opened on page `?page=3` keeps cycling page 3's posts.] → Intended; the URL defines the view.
- [Reloading feeds at every wrap on a short interval creates page-view load.] → With 5 flip pages × 30 s, that is one request every 2.5 min, which is negligible. The minimum interval of 5 s bounds it.

## Open Questions

- Whether the kiosk should also hide the menu button after inactivity. This can be added later without changing these requirements.
