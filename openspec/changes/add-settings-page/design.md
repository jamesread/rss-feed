# Design

## Context

- **Theme and background:**
  - `resources/views/app.blade.php` reads `config('app.theme')` and `config('app.dark_background')`.
  - It normalises both and writes `data-theme`, `data-dark-bg` and `--app-dark-bg` on `<html>`.
  - All dark surfaces derive from `--app-dark-bg` in CSS.
- **Feeds:**
  - Feeds come from the plaintext file at `config('app.config_file')`, which is `APP_CONFIG_FILE` and defaults to `storage/feeds.txt`.
  - `RssConfig` parses that file and `ConfiguredFeedProvider` loads it as a request singleton.
  - `RssConfig::toString()` already writes lines (name, `[colour]`, `site=`, tags, `-` prefix). But it writes names with spaces as-is, so a parsed name like `Acme News` would not survive a round trip.
- **Auto-advance:**
  - Flip auto-advance lives in `FlipView.vue`.
  - It is on when the `?autoplay` URL parameter is present (parsed in `Posts.vue`) or a `localStorage` flag set by the drawer toggle is `'1'`.
  - The wrap reload uses `router.reload({only: [...]})`.
- **Infrastructure:**
  - `HandleInertiaRequests` is not registered, so there are no shared props and no Inertia validation-error bag.
  - `starred.js` already talks to the server with `fetch` + JSON + the `X-XSRF-TOKEN` header from the cookie.
  - The Docker entrypoint creates `/app/storage/feeds.txt` and the SQLite DB, runs migrations and `chown`s `/app` to `www`. So both stores are writable and persisted on the storage volume.

## Goals / Non-Goals

**Goals:**
- One place that resolves effective settings (saved → env → built-in default), used by the Blade layout and by controllers.
- Feeds editing that round-trips through the existing file format and parser.
- Minimal changes to existing pages: a link, and auto-advance taken from a prop.

**Non-Goals:**
- A generic settings framework or schema.
- Moving other env options into the UI.
- Editing colour or hidden flags.
- Keeping comments in `feeds.txt`.
- Concurrency control between two people editing feeds at once (last save wins).

## Decisions

### 1. General settings in a key/value `settings` table

- New migration `create_settings_table`: `key` string primary/unique, `value` text, timestamps.
- Keys:
  - `theme`: `system|light|dark`.
  - `dark_background`: `#rgb|#rrggbb`. Absent means default.
  - `autoplay_enabled`: `0|1`.
  - `autoplay_seconds`: one of `10,15,30,45,60,120`.
- A small `App\Settings\AppSettings` class, bound as a singleton, loads all rows once per request and exposes:
  - `theme()`
  - `darkBackground()`
  - `autoplaySeconds()`: the interval when auto-advance is on, `null` when off.
  - `autoplayInterval()`
  - `set(array)` and `forget(key)`
- It resolves each value as saved value → `config('app.*')` → built-in default. The validation and normalisation currently in `app.blade.php` move into this class.
- If the table doesn't exist yet (for example, artisan commands before `migrate`), it falls back to env values instead of throwing.

*Alternatives:*
- **JSON file in storage:** a second persistence mechanism, with no transactions.
- **Overriding `config()` at boot:** hides the DB read in a provider. It breaks the "nothing saved → env" distinction the page needs, and runs on every artisan command.
- **The `.env` file itself:** cached by `config:cache` and not writable in the image.

### 2. Settings are written through JSON endpoints, not Inertia forms

- Routes:
  - `GET /settings` and `GET /settings/feeds` render the Inertia page `Settings` with a `section` prop.
  - `PUT /settings` saves General.
  - `PUT /settings/feeds` saves the feeds.
- The write routes return `204` on success or Laravel's standard `422` JSON with `errors` keyed by field (e.g. `rows.2.tags`). If the feeds file cannot be written, they return `500` with a `message`.
- The page sends requests with `fetch`. The cookie-reading and request code in `starred.js` moves to a shared `resources/js/http.js` that returns `{ok, status, data}`. `starred.js` keeps its boolean wrapper, so its behaviour and tests stay the same.
- After a successful General save, the page does a full `window.location.reload()`, because theme and background live on the server-rendered `<html>` element, outside Inertia. After a feeds save it shows "Saved" and reloads the `rows` prop.

*Alternatives:*
- **Registering `HandleInertiaRequests` and using `useForm`:** brings session error bags and redirects into an app that has none today, and touches every page.
- **Plain HTML form posts:** need server-rendered error pages.

### 3. Feeds rows round-trip through `RssConfig`

- **GET:** the controller parses the file with `RssConfig` (not through `ConfiguredFeedProvider`, which creates `Feed` rows and touches `last_accessed_at`). It sends `rows` with these fields:
  - `title`: the name with spaces.
  - `tags`: tags without `#`, joined by `, `.
  - `url`
  - `site`
  - `color`
  - `hidden`
- **Client:** keeps `color` and `hidden` in each row object without showing them, and sends them back unchanged. New rows get `color: ''` and `hidden: false`. Carrying them on the row, rather than merging by URL on the server, keeps them even when the URL of a row is edited.
- **PUT, validation:**
  - `rows` is an array of at most 1000 items.
  - `title` is required and has no `[`, `]` or line breaks.
  - `url` is required, matches `^https?://\S+$` and is distinct across rows.
  - `site` is nullable and matches `^https?://\S+$`.
  - `color` is nullable with no whitespace, `[` or `]`.
  - `hidden` is boolean.
- **PUT, tag parsing** happens on the server: split on `,`, trim, strip one leading `#`, drop empties, require at least one, reject any whitespace or `#` inside a tag, and prefix `#`.
- **Writing:** the controller builds a fresh `RssConfig` with `addFeed()` per row and writes `toString() . "\n"`.
- `RssConfig::toString()` changes to write spaces in names as `_`. `parseFromString()` already turns `_` into spaces, so the round trip is lossless. Existing `toString`/`encodeForUrl` tests use names without spaces and stay valid.

### 4. File write in place with a lock

- `file_put_contents($path, $content, LOCK_EX)`, after checking that the file is writable, or that its directory is writable when the file is missing.
- A temp-file + `rename()` would be atomic, but it fails or silently detaches when `feeds.txt` is bind-mounted as a single file, which is a common Docker setup. If the write returns `false`, respond `500` with "The feeds file could not be written" and leave the content untouched.
- The next request's `ConfiguredFeedProvider` reads the new file, creates `Feed` rows for new URLs, and fetches them as outdated, the same as when the file is edited by hand. Removed feeds keep their `Feed` and post rows, as they do today.

### 5. Auto-advance from the server

- `PostViewController`'s posts views and the starred view add the prop `autoplay`, which is the saved interval in seconds or `null` when auto-advance is off.
- `Posts.vue` passes it to `FlipView` as `savedAutoplaySeconds`. A pure helper in `flip/autoplay.js`, `resolveAutoplaySeconds(urlSeconds, savedSeconds)`, returns the interval in use (URL wins) or `null`. `FlipView` uses it for `autoplayEnabled` and the interval.
- The following are removed:
  - The drawer toggle markup.
  - `setAutoplay`, `readStoredAutoplay`, `autoplayStored`, `autoplayOffForView` and `AUTOPLAY_STORAGE_KEY`.
- The wrap reload's `only` list gets `'autoplay'`, so a kiosk picks up a changed setting at the next wrap. A watcher restarts the timer when the interval changes.
- Only the URL parameter switches the layout to flip. A saved setting does not.

### 6. Settings page layout

- `Settings.vue` reuses the two-column markup and Tailwind classes of `Posts.vue`:
  - The left pane uses the same sidebar container, background and dark surfaces, and contains « Back to the posts, then the menu entries as Inertia links to `settings` and `settings/feeds`. These are relative, so the `<base>` keeps proxy prefixes working.
  - The right pane holds the section content.
- The markup is copied, not extracted into a shared component, to keep `Posts.vue` untouched beyond the link.
- **General:**
  - The theme is a three-option segmented control or select.
  - The dark background is `<input type="color">`, with `#rgb` expanded to `#rrggbb` for the picker, plus a "Use default" checkbox that sends `null`.
  - Auto-advance is a checkbox.
  - The interval is a `<select>` with labels `10 s, 15 s, 30 s, 45 s, 1 min, 2 min`.
  - One Save button.
- **Feeds:**
  - A table on wide screens and stacked cards on narrow ones.
  - Inputs carry `required` for the first three fields.
  - Next to the Tags header is an info icon (`<button type="button">` with `title` and an `aria-describedby` tooltip), shown on hover and focus: "Separate several tags with commas, e.g. tag_1, tag_2".
  - "Add feed" appends a row, and a remove button sits on each row.
  - One Save button. Server errors appear under the matching inputs.
- The sidebar link "Settings" goes into `Posts.vue`'s sidebar. That sidebar is teleported into the flip drawer, so one link covers both places in the spec.

## Risks / Trade-offs

- **Anyone on the network can change feeds and settings:** this is the user's choice. The page is no more exposed than stars, and CSRF protection blocks cross-site forgery. The README states it.
- **Comments in `feeds.txt` are lost on the first save from the page:** the README warns about it. The page shows a one-line note above the Save button.
- **Saving General applies to all devices,** including auto-advance on phones: this is the user's choice. The URL parameter remains the per-device override.
- **Two editors save feeds at once,** or the file is hand-edited while the page is open: last write wins. Acceptable for a single-operator tool.
- **The settings table is missing on a fresh install:** `AppSettings` falls back to env, and the entrypoint runs migrations before serving.
- **`config:cache` in the image:** unaffected, because saved settings are read from the DB per request.

## Migration Plan

- Deploy as usual: `docker compose pull && docker compose up -d`. The entrypoint runs the new migration.
- Nothing is saved initially, so `APP_THEME` and `APP_DARK_BACKGROUND` keep applying until the operator saves the General menu.
- Browsers that had the drawer toggle switched on stop auto-advancing unless the URL has `?autoplay`, or until auto-advance is switched on in Settings.
- **Rollback:** the previous image ignores the `settings` table. `feeds.txt` written by the page is valid for every version.
