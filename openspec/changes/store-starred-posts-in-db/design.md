# Design

## Context

Current state (from `add-starred-posts`):

- `resources/js/starred.js` holds a Vue `reactive` store on top of `localStorage` (key `starred-posts`). `snapshotPost()` copies `url`, `title`, `description` (max 500 chars), `published_at`, `thumbnail`, `source`, `starred_at`, `feed {name, color, url, tags, favicon}` and `feeds [{name, color, url}]`. The store exposes `list()`, `count()`, `isStarred(url)`, `toggle(post)`, `reload()`, and a `storage` event listener syncs tabs.
- `StarButton.vue` calls `starred.toggle(post)` and `starred.isStarred(url)`. `Posts.vue` shows `starredStore.list()` when the `starred` prop is set and `Starred ({{ starredCount }})` in the sidebar. `FlipView.vue` gets `posts` and `starred` as props.
- `PostViewController::starred()` renders `Posts` with `posts: []`, `starred: true`. All other views go through `renderPostsView()`.
- `Post` hides `id`, so the browser identifies posts by `url` only.
- Routes are in the `web` middleware group, which already runs sessions (file driver), cookie encryption and Laravel's CSRF check, and sets the `XSRF-TOKEN` cookie. There are no write routes yet. `HandleInertiaRequests` exists but is not registered, so there are no shared props.
- The database is SQLite in `/app/storage`, migrated on container start like the other tables.

## Goals / Non-Goals

**Goals:**
- One server-side star list, keyed by URL, holding the same snapshot as today.
- Minimal frontend churn: keep the store API so `StarButton`, `Posts` and `FlipView` change little.
- Lossless one-time move of existing browser stars.

**Non-Goals:**
- Users, logins or per-device lists; a toggle to keep browser storage.
- Live push of changes to other open tabs or devices.
- Search, time filter or paging in the starred view.
- Fetching a thumbnail that was never downloaded for the post.

## Decisions

### 1. `starred_posts` table with snapshot columns
Migration `create_starred_posts_table`: `id`, `url` (string, unique), `title` (string), `description` (text, max 500), `published_at` (integer), `thumbnail` (string, default `''`), `source` (string, default `''`), `feed` (JSON), `feeds` (JSON), `starred_at` (integer, indexed), timestamps. Model `StarredPost` casts `feed` and `feeds` to arrays and hides `id` and timestamps, so its JSON matches today's snapshot shape and the post components render it unchanged.

- Alternative: a `post_id` foreign key. Rejected because stars must outlive pruned posts and removed feeds.

### 2. The client sends the snapshot; the server validates it
`POST /starred` takes one snapshot as JSON. `POST /starred/import` takes `{posts: [snapshot, …]}`. Both use one Form Request/validator: `url` required, max 2048, must start with `http://` or `https://`; `title` required string max 1000; `description` string, truncated to 500; `published_at` and `starred_at` integers (import keeps `starred_at`, a single star uses the server's `time()`); `thumbnail`, `source` strings; `feed` and `feeds` arrays with only the known keys kept. Rows are written with `insertOrIgnore` on `url`, so repeated stars and overlapping imports keep the first copy and its star time. Both return `204`.

- Alternative: the server builds the snapshot from the `posts` table by URL. Rejected because the import must accept stars whose posts are already pruned, and one path for both keeps it simple. Content is shown through Vue text bindings, and the `http(s)` check keeps `javascript:` links out.

### 3. `DELETE /starred` with `{url}` in the JSON body
Deletes the row if present and returns `204` either way, so unstarring twice is not an error.

### 4. CSRF through the existing `XSRF-TOKEN` cookie
`starred.js` sends requests with `fetch`, `Content-Type: application/json`, `Accept: application/json` and `X-XSRF-TOKEN` set to the decoded `XSRF-TOKEN` cookie. Laravel's CSRF middleware accepts this header. No new dependency (no axios).

- Alternative: Inertia `router.post`. Rejected because it triggers a page visit and prop reload for every tap.

### 5. Server state reaches the page as props
- `renderPostsView()` and `starred()` add `starredUrls`: all starred URLs (a list of strings).
- `starred()` sets `posts` to all `StarredPost` rows ordered by `starred_at` desc.
- `Posts.vue` calls `starred.load(starredUrls)` on creation and when the prop changes (Inertia navigations and the flip autoplay reload refresh it). In the starred view it shows `posts` filtered by `starred.isStarred(url)`, so an unstarred post drops out without a reload.

### 6. Store keeps its API, backed by requests
`createStarredStore({request, storage, now})` holds `state.urls` (a `Set`-like array). `toggle(post)` updates `state.urls` at once, sends `POST` or `DELETE`, and on a non-2xx response or network error restores the previous state. `count()` is `state.urls.length`. `list()` is no longer needed. `request` is injected so `tests/js/starred.test.js` can test without a network. The `storage` event listener is removed.

### 7. One-time import
On creation, `starred.js` reads `localStorage['starred-posts']`. If it holds posts, it sends them to `POST /starred/import`. On `204` it removes the key and adds the URLs to the store, so the count is right without a reload; on failure it keeps the key and tries again on the next page load. Storage access stays wrapped in `try/catch`. To show imported stars in the starred view at once, the import calls `router.reload()` when the current page is `/starred`.

### 8. Kept thumbnail copies
Post thumbnails live on the public disk at `thumbs/{feed_id}/{post_id}.{ext}` and `PostPruner` deletes them with the post. On star and import, the server checks that the submitted `thumbnail` is a path under `thumbs/` (no `..`) that exists on the public disk, copies it to `starred/{sha1(url)}.{ext}`, and stores that path in the row; otherwise it stores `''`. `destroy` deletes the copy. The UI keeps using `storage/{thumbnail}`, so no frontend change is needed, and the RSS feed change can publish the image.
- Alternative: excluding starred posts' thumbnails from pruning. Rejected because it couples the pruner to stars and still loses the image when the post row is deleted.

## Risks / Trade-offs

- [Anyone who can reach the site can change the shared list] → Accepted for a single-user homelab app without logins. Called out in the README and as BREAKING in the proposal.
- [All starred URLs are sent with every page] → About 100 bytes per star; a few hundred stars add only tens of KB. Paging can follow if it ever matters.
- [Session and CSRF cookies now matter] → Sessions already run on every request (`storage/framework/sessions`). A kiosk with blocked cookies can still read; starring fails and rolls back.
- [Kept thumbnails use disk space] → One small image per star; deleted on unstar.
- [Import runs on several devices at once] → The unique URL index and `insertOrIgnore` make it idempotent.
- [Behind a reverse proxy on another path] → Requests use relative URLs (`starred`, `starred/import`) like the existing `feed?url=` call, so the `<base href>` keeps them on the app path.

## Migration Plan

Deploy normally; the container runs migrations on start. Each browser imports its local stars on its first visit. Rollback: revert the commit. The table stays (harmless), and browsers that already imported have lost their local copy, so before rolling back, export with `sqlite3 /app/storage/database/database.sqlite "select * from starred_posts"` if the stars matter.

## Open Questions

None.
