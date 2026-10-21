# Proposal

## Why

Stars live in each browser's local storage. A post starred on the phone is missing on the laptop or the kiosk, and clearing site data loses every star. The reader runs as a single-user homelab app, so one list kept on the server, next to the feeds and posts, fits better.

## What Changes

- Starred posts are stored in a new `starred_posts` table in the app's SQLite database. Each row keeps the same snapshot as today (URL, title, description, publish time, thumbnail, source, feed and feed list, star time), so stars still survive post pruning and feed removal.
- Starring and unstarring send a request to the server. Every device and browser sees the same list on its next page load.
- **BREAKING**: there is one shared list for everyone who can open the site, because the app has no users or logins. A star set on one device appears on all of them.
- The `/starred` view and the `Starred (n)` count come from the database instead of the browser.
- Stars already kept in a browser's local storage are copied to the server once, the first time that browser opens the app after the update. The local copy is then removed.
- When a post is starred, its thumbnail image is copied into a separate folder for stars, so it stays available after the post and its original thumbnail are pruned. Unstarring deletes the copy.
- The README is updated: the fork-features bullet, the Features list and the starred-posts description say stars are kept on the server in `/app/storage`, shared by every device and everyone who can open the site, and that existing browser stars are imported once.

Out of scope: per-user lists or authentication, an environment flag to keep browser storage, live sync between open tabs (other devices update on reload), search and time filter in the starred view.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `starred-posts`: stars are kept on the server and shared across devices instead of per browser, and existing browser stars are imported once. The capability is defined in the unarchived change `add-starred-posts`, which must be archived first.

## Impact

- Database: new migration and `StarredPost` model (`starred_posts` table, unique by URL).
- Routes and controllers: `POST /starred`, `DELETE /starred`, `POST /starred/import` (JSON, protected by Laravel's CSRF check); `/starred` renders the stored posts; every posts page passes the starred URLs and count.
- Frontend: `resources/js/starred.js` sends requests instead of writing local storage and runs the one-time import; `StarButton.vue`, `Posts.vue` and `FlipView.vue` read the server-provided state.
- Tests: new feature tests for the routes and the table, updated `PostViewControllerTest`, updated `tests/js/starred.test.js`.
- Thumbnails: starred copies under `starred/` on the public disk (served from `storage/starred/…`), deleted on unstar.
- Docs: README fork-features bullet, Features list and starred-posts description.
