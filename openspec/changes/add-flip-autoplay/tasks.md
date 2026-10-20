# Tasks

## 1. Timing logic

- [ ] 1.1 `resources/js/flip/autoplay.js`: `parseAutoplayParam()` (missing → null, empty/invalid/out of 5–3600 → 30, valid → value) and the state helpers (`shouldAdvance`, `progress`, pause until). Verify: `npm test` cases for `?autoplay`, `?autoplay=45`, `?autoplay=2`, `?autoplay=abc`, no param, advance after the interval, no advance while paused, progress reset.

## 2. Flip view behavior

- [ ] 2.1 `Posts.vue`: an `autoplay` URL param forces the flip layout for this view (without overwriting `post-format`) and passes the interval to `FlipView`. Verify: in a fresh browser profile `/?autoplay` opens in flip view.
- [ ] 2.2 `FlipView.vue`: 250 ms tick advancing `current` and looping to page 0; pause on drawer open, hidden tab, and touch/wheel/key/drag input (60 s), ignoring self-caused scroll events; instant turns under reduced motion. Verify: in the browser with `?autoplay=5`, pages advance every 5 s, loop after the last, stop while the drawer is open, and resume 60 s after a swipe.
- [ ] 2.3 Wrap reload: `router.reload({only: ['posts', 'feeds']})` (plus `hasMore` if present) at each loop, keeping the URL params; on failure keep the old posts. Verify: with `?autoplay=5` and a new post inserted in the DB, the post appears after the next wrap; with the server stopped, the view keeps cycling the old posts.
- [ ] 2.4 Drawer `Auto-advance` toggle stored in `localStorage` (`flip-autoplay`), reflecting URL activation; off stops it for the current view. Verify: toggling on persists across a reload; toggling off with `?autoplay` stops advancing and hides the bar.
- [ ] 2.5 Progress bar along the bottom inside the safe area, below the page indicator, both themes, hidden when off. Verify: in the browser the bar fills over the interval, resets on a page change, shows no progress while paused, and does not overlap tile text or the indicator.

## 3. Docs

- [ ] 3.1 README: describe auto-advance and a kiosk example (`chromium --kiosk "https://<host>/t/Ecosystem?autoplay=30"`). Verify: README renders the new section.

## 4. Verification

- [ ] 4.1 Gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm test`, `npm run build`. All pass.
- [ ] 4.2 Kiosk soak test in a test container (not `rss-flip`/8081): Chrome in a kiosk-like window on `/?autoplay=10` for 30 minutes. Verify: it keeps cycling, new posts appear after wraps, and memory in the browser task manager stays roughly flat.
