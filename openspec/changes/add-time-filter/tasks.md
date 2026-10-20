# Tasks

## 1. Server

- [ ] 1.1 `PostViewController::renderPostsView()`: map `since` (`24h`→86400, `7d`→604800, `30d`→2592000; anything else → none) to a `published_at >= now - period` condition combined with the search condition; pass `since` (normalized, `''` when none) to the page. Verify: `PostViewControllerTest` cases for `24h`, unknown value, and `since` + `query` on a tag view.

## 2. UI

- [ ] 2.1 `Posts.vue`: segmented control `All · 24 h · 7 days · 30 days` under the search field, active option highlighted, links reloading the current path with `since` (and `query`) at page 1, marked `data-flip-keep-drawer`. Verify: in the browser choosing `7 days` on a tag view updates the URL and list and highlights the option.
- [ ] 2.2 `pageData()` in `Posts.vue` and `FlipView.vue`, and `submitSearch()`, keep `since`; flip menu label appends ` · 24 h` / ` · 7 days` / ` · 30 days`. Verify: `npm test` (or a component test) for `pageData` with search + since; in the browser `Next Page` keeps `since` and the flip label shows the filter.
- [ ] 2.3 README Features: mention the time filter. Verify: README renders the new bullet.

## 3. Verification

- [ ] 3.1 Gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm test`, `npm run build`. All pass.
