# Tasks

## 1. Database

- [x] 1.1 Migration `create_starred_posts_table` and model `StarredPost` per design.md decision 1 (unique `url`, indexed `starred_at`, JSON `feed`/`feeds` cast to arrays, `id` and timestamps hidden). Verify: `php artisan migrate:fresh` on a scratch SQLite DB succeeds, and a test that creates a row and serialises it gets the snapshot keys (`url`, `title`, `description`, `published_at`, `thumbnail`, `source`, `starred_at`, `feed`, `feeds`) and no `id`

## 2. Server routes

- [x] 2.1 `StarredPostController` with `store` (`POST /starred`), `destroy` (`DELETE /starred`) and `import` (`POST /starred/import`), sharing one snapshot validation per design.md decisions 2 and 3 (`insertOrIgnore` by URL, description truncated to 500, `http(s)` only, unknown feed keys dropped, `204` responses). Verify: new `tests/Feature/StarredPostControllerTest.php` covers star, star twice (one row, original `starred_at`), unstar, unstar missing (204), `javascript:` URL rejected (422, no row), import of 3 with one already present (3 rows, existing unchanged, imported `starred_at` kept), and a request without the CSRF token rejected (419) when the CSRF middleware is not bypassed
- [x] 2.2 `PostViewController`: `renderPostsView()` and `starred()` pass `starredUrls`; `starred()` passes all `StarredPost` rows as `posts`, newest `starred_at` first. Update the doc comment on `starred()`. Verify: `PostViewControllerTest` asserts `starredUrls` on `/` and `/t/{tag}`, and `/starred` lists stored rows in `starred_at` order, including a row whose post no longer exists

- [x] 2.3 Kept thumbnails per design.md decision 8 in `store`, `import` and `destroy`. Verify: `StarredPostControllerTest` with a fake public disk covers: star copies `thumbs/1/2.jpeg` to `starred/<sha1>.jpeg` and stores that path; a missing file or a path outside `thumbs/` (e.g. `../.env`) stores `''`; unstar deletes the copy; running `PostPruner` afterwards leaves the copy in place

## 3. Frontend

- [x] 3.1 Rewrite `resources/js/starred.js` per design.md decisions 4 and 6: URL state loaded from props, optimistic `toggle(post)` that sends `POST`/`DELETE` with the `X-XSRF-TOKEN` header and rolls back on failure, `count()`, `isStarred(url)`, injected `request` for tests; remove the `storage` listener. Verify: `tests/js/starred.test.js` updated for star, unstar, rollback on a rejected request and on a thrown network error, `load()` replacing the state, and the request carrying the decoded `XSRF-TOKEN` cookie
- [x] 3.2 One-time import in `starred.js` per decision 7: send `localStorage['starred-posts']` to `starred/import`, remove the key and add the URLs on success, keep it on failure, `router.reload()` when on `/starred`, all storage access in `try/catch`. Verify: `npm test` cases for success (key removed, URLs added), failure (key kept), empty or invalid stored JSON (no request), and a throwing `localStorage`
- [x] 3.3 `Posts.vue` and `FlipView.vue`: accept `starredUrls`, call `starred.load()` on creation and when the prop changes, show `posts` filtered by `starred.isStarred()` in the starred view, keep `Starred (n)` from `starred.count()`. Update the "Starred posts live in the browser" comment. Verify: `npm run build` and `npm test` pass, and `grep -rn "localStorage" resources/js/starred.js` only finds the import code

## 4. Documentation

- [x] 4.1 README: update the fork-features bullet ("Starred posts"), the Features entry ("Starred / read-later posts") and add a short "Starred Posts" section: stars are stored on the server in `/app/storage` (database plus kept thumbnails), shared by every device and everyone who can open the site, survive pruning, and existing browser stars are imported on each browser's first visit after updating. Verify: `grep -n -i star README.md` shows the updated entries and the README no longer says stars are kept in the browser or are per device

## 5. Integration checks

- [x] 5.1 Run `php artisan test`, `vendor/bin/phpstan analyse --memory-limit=1G` and `npm test`, and verify all pass
- [x] 5.2 Headless browser check (Playwright Chromium from the local cache against `php artisan serve` with a scratch SQLite DB and seeded posts, or a podman test container not named `rss-flip`/`rss-local` and not on 8081/8082). Check: (a) a browser with 2 stars pre-seeded in `localStorage` opens `/`; afterwards `Starred (2)` shows, the key is gone, and the rows are in the DB; (b) a second, fresh browser context sees the same 2 stars filled and listed on `/starred`; (c) starring in card view and on a flip tile, then reloading in the other context, shows the new star; (d) unstarring in `/starred` removes the post at once; (e) with the server stopped, tapping a star rolls it back without console errors. Clean up servers, DB and containers afterwards

## Workflow follow-up

- Archive `add-starred-posts` before this change, because this change modifies and removes its `starred-posts` requirements.
- After deploying, open https://news.local.michard.cc/ in each browser that has stars so they get imported.
