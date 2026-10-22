# Proposal

## Why

Changing the theme, the dark background or the feed list means editing `.env` or `feeds.txt` on the host and restarting or reloading the container. Auto-advance can only be switched per browser or through a URL parameter. A settings page in the app makes these changes possible from any browser, and the changes stay in place when the container restarts.

## What Changes

- New page `GET /settings` in the same layout as the list, card, compact and grid views: a left pane with a menu, and the selected menu's content on the right. The menu has room for more entries later. This change adds two: **General** and **Feeds**.
- The posts views' sidebar and the flip view drawer link to the settings page.
- **General** menu:
  - Theme: `System`, `Light` or `Dark`.
  - Dark background colour, with a way to go back to the default.
  - Auto-advance on or off.
  - Auto-advance interval: 10 s, 15 s, 30 s, 45 s, 1 min or 2 min.
- **Feeds** menu: the feed list as editable rows, with one row per feed.
  - Each row has a title, tags, the feed URL and an optional picture origin, which is the `site=` value used for the favicon.
  - Title, tags and URL are required.
  - Tags are comma-separated, and `tag_1,tag_2` and `tag_1, tag_2` are both accepted.
  - An info icon next to the tags heading explains the comma separation when hovered.
  - Rows can be added and removed. Saving rewrites `feeds.txt`.
- Settings are saved on the server: General settings in the SQLite database, and feeds in the feeds file. Both live in `/app/storage`, so they survive a container restart or re-creation.
- A saved theme or dark background overrides `APP_THEME` or `APP_DARK_BACKGROUND`. Until something is saved, the environment variables keep working as before.
- **BREAKING (behaviour):** auto-advance becomes one setting for the whole installation.
  - The `Auto-advance` toggle in the flip view drawer is removed, together with its per-browser memory.
  - When auto-advance is on in the settings, the flip view turns pages on every device.
  - The `?autoplay` URL parameter still works and still wins for that page view.
- Feed rows keep any colour (`[#hex]`) and hidden flag (`-` prefix) they already have in `feeds.txt`, unchanged, but the rows don't show them. New rows have neither.
- **BREAKING (file content):** comment lines and blank lines in `feeds.txt` are lost when the feed list is saved from the settings page. Names are written with underscores in place of spaces, as the file format requires.
- README: settings page section, and notes in Feed Configuration, App Configuration and Kiosk sections.

Decided with the user:
- No access protection: the page is as open as the rest of the app.
- Auto-advance is controlled by the server only.
- Colour and hidden flags are kept but not editable.
- Only the dark background is configurable.

Out of scope:
- Editing colour or hidden flags in the UI.
- A light-theme background.
- Other env settings, such as thumbnails, update frequency and pruning.
- Authentication.
- Live push of changes to other open browsers: they pick up changes on their next page load or auto-advance reload.

## Capabilities

### New Capabilities
- `app-settings`: the settings page (layout, menus, General settings), how settings are stored and how they combine with environment variables.

### Modified Capabilities
- `feed-config`: the feed list can be viewed and edited from the settings page and is written back to the feeds file.
- `ui-theme`: a theme and dark background saved in the settings take precedence over `APP_THEME` and `APP_DARK_BACKGROUND`.
- `post-flip-view`: auto-advance follows the server setting. The drawer toggle is removed.

## Impact

- **Backend:**
  - New `settings` table (migration) and `Setting` model.
  - New `SettingsController`, with routes `GET /settings`, `PUT /settings` and `PUT /settings/feeds`.
  - `RssConfig` gets a writer that keeps colour and hidden flags.
  - A small settings reader is used by `app.blade.php` and `PostViewController`.
- **Frontend:**
  - New Inertia page `Settings.vue`.
  - `Posts.vue` and `FlipView.vue`: settings link, auto-advance taken from a prop instead of `localStorage`, drawer toggle removed.
- **Files:** `APP_CONFIG_FILE` must be writable by the app user. The Docker image already creates it in `/app/storage` and gives it to `www`.
- **Tests:** feature tests for the settings routes, the precedence over env and the feeds round trip; a unit test for config writing; JS tests where logic moves.
- **Docs:** README.
