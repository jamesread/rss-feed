# Spec Delta

## Purpose

Lets an operator change how the installation looks and behaves from a settings page in the app, instead of editing environment variables or files on the host, with changes kept across container restarts.

## ADDED Requirements

### Requirement: Settings page at /settings
The application SHALL serve a settings page at `/settings`. Its layout SHALL match the list, card, compact and grid views. A left pane holds a menu. The content of the selected menu is shown to the right of it, or below the menu on narrow screens. The page SHALL offer the menus `General` and `Feeds`, with `General` selected on first open. The page SHALL work under a proxy sub-path like the other pages.

#### Scenario: Open settings
- **WHEN** a reader opens `/settings`
- **THEN** the left pane lists `General` and `Feeds` and the right side shows the General settings

#### Scenario: Switch menu
- **WHEN** a reader selects `Feeds` in the left pane
- **THEN** the right side shows the feed list, `Feeds` is marked as selected, and the URL identifies the menu so a reload shows it again

#### Scenario: Back to the posts
- **WHEN** a reader is on the settings page
- **THEN** a link in the left pane leads back to the posts home page

### Requirement: Settings page is reachable from the posts views
The sidebar of the list, card, compact and grid views and the flip view drawer SHALL each contain a link to the settings page.

#### Scenario: From the sidebar
- **WHEN** a reader clicks `Settings` in the sidebar of the list view
- **THEN** the settings page opens

#### Scenario: From the flip drawer
- **WHEN** a reader opens the flip view drawer and clicks `Settings`
- **THEN** the settings page opens

### Requirement: General settings offer theme, dark background and auto-advance
The General menu SHALL offer these settings, each showing its current effective value:
- The theme, as a choice of `System`, `Light` and `Dark`.
- The dark background colour, as a colour picker plus an option to use the default.
- Auto-advance on or off.
- The auto-advance interval, as a choice of 10 s, 15 s, 30 s, 45 s, 1 min and 2 min, with 30 s as the default.

#### Scenario: Current values shown
- **WHEN** `APP_THEME=dark` is set, nothing is saved, and a reader opens the General menu
- **THEN** the theme choice shows `Dark`, auto-advance shows off and the interval shows 30 s

#### Scenario: Interval choices
- **WHEN** a reader opens the interval choice
- **THEN** exactly the options 10 s, 15 s, 30 s, 45 s, 1 min and 2 min are offered

### Requirement: General settings are saved on the server
Saving the General menu SHALL store the values on the server and apply them to every page and every device from the next page load. The settings page itself SHALL show the new theme and background right after saving. The server SHALL reject values outside the offered choices, or a colour that is not `#rgb` or `#rrggbb`, and keep the previous values. Choosing the default dark background SHALL remove the saved colour.

#### Scenario: Save theme
- **WHEN** a reader selects `Light` and saves
- **THEN** the settings page and every page loaded afterwards, on any device, use the light theme

#### Scenario: Invalid interval rejected
- **WHEN** a save request sends an auto-advance interval of 7 seconds
- **THEN** the request is rejected with a validation error and the stored interval is unchanged

#### Scenario: Back to default background
- **WHEN** a dark background was saved and a reader chooses the default and saves
- **THEN** the dark background falls back to `APP_DARK_BACKGROUND`, or to the built-in default when that is unset

### Requirement: Saved settings survive a container restart
General settings SHALL be stored in the application database within the storage directory, and feeds in the feeds file. Restarting or recreating the container with the same storage volume SHALL keep all saved settings and feeds.

#### Scenario: Container recreated
- **WHEN** the theme was saved as `Dark`, the interval as 45 s and a feed was added, and the container is recreated with the same `/app/storage` volume
- **THEN** the theme is dark, the interval is 45 s and the added feed is listed

### Requirement: Settings changes are protected against cross-site requests
Requests that change settings or feeds SHALL require the site's CSRF token. A request without it SHALL be rejected and change nothing. The settings page SHALL have no login, like the rest of the application.

#### Scenario: Forged request
- **WHEN** a request to save settings arrives without the CSRF token
- **THEN** it is rejected and the stored settings are unchanged
