# Tasks

## 1. Settings storage and effective values

- [x] 1.1 Migration `create_settings_table` (key/value) and the `AppSettings` singleton per design.md decision 1: saved → `config('app.*')` → default, with theme/background normalisation moved out of `app.blade.php`, and env fallback when the table is missing. Verify: new `tests/Feature/AppSettingsTest.php` covers each key resolving saved value over env over default, invalid saved/env values falling back, and no exception when the `settings` table is dropped
- [x] 1.2 `app.blade.php` reads theme and dark background from `AppSettings`. Verify: existing `ThemeConfigTest` and `DarkBackgroundConfigTest` still pass, and new cases assert that a saved `light` theme beats `APP_THEME=dark` and a saved `#000000` beats `APP_DARK_BACKGROUND` in the rendered `<html>` (ui-theme spec scenarios)

## 2. Settings page and General menu

- [x] 2.1 Routes `GET /settings`, `GET /settings/feeds` and `PUT /settings`, plus `SettingsController` (show and General save) per decisions 2 and 1. Validation: theme in `system|light|dark`, `dark_background` null or hex, `autoplay_enabled` boolean, `autoplay_seconds` in `10,15,30,45,60,120`. Null background forgets the key. Verify: new `tests/Feature/SettingsControllerTest.php` asserts the Inertia component `Settings` with the `section` prop and effective General values (env values when nothing is saved), a valid save (204) persisting all four, an interval of 7 and a theme of `blue` giving 422 with nothing changed, a null background removing the saved colour, and a save without CSRF token in production env giving 419
- [x] 2.2 Extract cookie and JSON request code from `starred.js` into `resources/js/http.js` (returns `{ok, status, data}`); `starred.js` keeps its boolean wrapper. Verify: `tests/js/starred.test.js` passes unchanged, and a new `tests/js/http.test.js` covers the XSRF header and parsing of a 422 JSON body
- [x] 2.3 `resources/js/Pages/Settings.vue`: two-pane layout reusing the `Posts.vue` sidebar classes, back link, `General`/`Feeds` menu links (relative URLs), General form (theme control, colour picker plus "Use default", auto-advance checkbox, interval select with the six labels), save via `http.js`, error display, full reload after success, per decision 6. Verify: `npm run build` passes, and in a browser at a scratch port `/settings` shows both menus. Changing to `Light` and saving re-renders light; `/settings/feeds` reload keeps the Feeds menu selected
- [x] 2.4 `Settings` link in the `Posts.vue` sidebar (also shown in the flip drawer through the teleport). Verify: in a browser the link opens `/settings` from the list view and from the flip drawer
- [x] 2.5 README: new "## Settings" section that describes:
  - The menus.
  - That changes apply to all devices and persist in `/app/storage`.
  - That a saved theme or background overrides `APP_THEME` / `APP_DARK_BACKGROUND`.
  - That there is no login.

  Also add a Features entry and notes on the `APP_THEME` and `APP_DARK_BACKGROUND` lines in App Configuration. Verify: `grep -n -i "settings" README.md` shows the section, the Features entry and both notes

## 3. Feeds menu

- [x] 3.1 `RssConfig::toString()` writes spaces in names as `_` (decision 3). Verify: a new `RssConfigTest` case shows a parsed line with `Acme_News[#f00] site=https://x.example` and a hidden flag round-trips through `toString()` and `parseFromString()` unchanged, and existing tests pass
- [x] 3.2 Rows prop for `GET /settings/feeds` (parsed with `RssConfig`, no `Feed` rows created) and `PUT /settings/feeds`, per decisions 3 and 4:
  - Validation and server-side tag parsing.
  - In-place `LOCK_EX` write.
  - A 500 with a message when the file is not writable.

  Verify: `SettingsControllerTest` against a temp `app.config_file` covers:
  - Rows read from a file with comments, colour, hidden and `site=` (and an empty or missing file giving `[]`).
  - Tags `tag_1,tag_2`, `tag_1, tag_2` and ` #tag_1 , tag_2, ` all saved as `#tag_1 #tag_2`.
  - 422 errors keyed `rows.N.field` for an empty title, empty tags, a non-http URL, a tag with a space, a title with `[`, and a duplicate URL, with the file unchanged.
  - Colour and hidden kept on an edited row (spec scenario line), and comments dropped.
  - A read-only file giving 500 with the file unchanged.
  - The saved feed appearing in `GET /` props.
- [x] 3.3 Feeds menu in `Settings.vue`:
  - Rows with Title, Tags, Feed URL (required) and Picture origin (optional), as a table on wide screens and stacked on narrow ones.
  - A Tags info icon whose hint shows on hover and focus.
  - Add and remove row, Save.
  - Per-field server errors.
  - A note that comments are not kept.
  - Hidden `color`/`hidden` carried on the row objects.

  Verify, in a browser at a scratch port with a temp feeds file:
  - Add a row with tags `a, b` and save. The file has `#a #b`, and the feed shows in the posts sidebar.
  - Remove a row and save. The feed is gone.
  - Submit an empty tags field. The error shows under that field and the file is unchanged.
  - The hint is visible on hover and on Tab focus.
  - The layout has no horizontal scroll at 375 px width.
- [x] 3.4 README: in Feed Configuration, mention editing via Settings → Feeds, that comments and blank lines are dropped on save, that colour/hidden are kept but only editable in the file, and that the file must be writable. Verify: `grep -n "Settings" README.md` shows the note in Feed Configuration

## 4. Auto-advance from the server

- [x] 4.1 `resolveAutoplaySeconds(urlSeconds, savedSeconds)` in `flip/autoplay.js` (decision 5). Verify: `tests/js/autoplay.test.js` covers URL only, saved only, both (URL wins) and neither (`null`)
- [x] 4.2 `autoplay` prop from `PostViewController` (posts views and starred view). `Posts.vue` passes it to `FlipView`, which uses the resolver. Drawer toggle, localStorage flag and related code removed. `'autoplay'` added to the wrap reload `only` list, and the timer restarts when the interval changes. Verify:
  - `PostViewControllerTest` asserts `autoplay` is `null` by default and `15` after saving on with 15 s, on `/` and `/starred`.
  - `npm test` and `npm run build` pass.
  - In a browser with auto-advance saved on at 10 s, flip view advances after about 10 s without the URL parameter, and the list layout stays a list.
  - With the setting off, no progress bar appears.
  - `?autoplay=45` still uses 45 s.
  - The drawer has no Auto-advance toggle.
- [x] 4.3 README Kiosk section: auto-advance is switched on and its interval chosen in Settings → General for all devices. The drawer toggle is gone. `?autoplay` still overrides per page view. Verify: the section no longer mentions the drawer toggle (`grep -n "Auto-advance\` toggle" README.md` returns nothing)

## 5. Integration checks

- [x] 5.1 Run `php artisan test`, `vendor/bin/phpstan analyse --memory-limit=1G` and `npm test`, and verify all pass
- [x] 5.2 Persistence check in a test container (not named `rss-flip`/`rss-local`, not on ports 8081/8082) built from the working tree with a temporary storage volume. Save theme `Dark`, background `#000000`, auto-advance on at 45 s, and add a feed. Then recreate the container with the same volume and verify that all four are still in effect and `feeds.txt` on the volume contains the new line. Remove the container, volume and image afterwards

## Workflow follow-up

- After deploying on devbox (`docker compose pull && docker compose up -d`), open https://news.local.michard.cc/settings. Check that the feed list matches the current `feeds.txt`, and back up that file before the first save, because its comments will be dropped.
- Archive after the earlier ui-theme and post-flip-view changes are archived (add-theme-env-config → add-dark-background-env → extend-dark-background-surfaces; add-flip-layout → add-flip-autoplay), so the REMOVED requirement has a main spec to apply to.
