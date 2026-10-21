# Spec Delta

## ADDED Requirements

### Requirement: Dark page background is configurable via APP_DARK_BACKGROUND
The application SHALL read the dark theme's page background colour from the `APP_DARK_BACKGROUND` environment variable, settable in the `.env` file or as a container environment variable. The value SHALL be a hex colour in `#rgb` or `#rrggbb` form, case-insensitive, with the leading `#` optional and surrounding whitespace ignored.

#### Scenario: Set in .env
- **WHEN** `.env` contains `APP_DARK_BACKGROUND="#000000"` and a page is shown with the dark theme active
- **THEN** the page background is `#000000`

#### Scenario: Set as container environment variable
- **WHEN** the Docker container is started with `APP_DARK_BACKGROUND=#1a1a2e` in its environment and a page is shown with the dark theme active
- **THEN** the page background is `#1a1a2e`

#### Scenario: Short form and no leading hash
- **WHEN** `APP_DARK_BACKGROUND` is `000` or `#FFF` or ` 123abc `
- **THEN** the dark page background is `#000`, `#fff` or `#123abc` respectively

### Requirement: Default dark page background is unchanged
When `APP_DARK_BACKGROUND` is unset or empty, the dark theme's page background SHALL be the same colour as before this change (Tailwind `gray-900`, about `#101828`).

#### Scenario: Unset
- **WHEN** `APP_DARK_BACKGROUND` is not set and the dark theme is active
- **THEN** the page background is identical to the background before this change

### Requirement: Invalid dark page background falls back to the default
When `APP_DARK_BACKGROUND` is not a valid hex colour as defined above, the application SHALL ignore it, use the default dark page background, and still render the page normally. The configured value SHALL never be written into the page unvalidated.

#### Scenario: Not a hex colour
- **WHEN** `APP_DARK_BACKGROUND` is `blue`, `#12345`, `#gggggg`, `rgb(0,0,0)` or `#000;color:red`
- **THEN** the page loads successfully with the default dark page background

### Requirement: Dark page background applies only to the dark theme's page background
The configured colour SHALL be used as the page background whenever the dark theme is active, whether forced by `APP_THEME=dark` or chosen by `APP_THEME=system` on a browser that prefers dark. It SHALL have no visible effect while the light theme is active, and SHALL NOT change the sidebar, cards, tiles, controls or the flip view.

#### Scenario: Forced dark theme
- **WHEN** `APP_THEME=dark` and `APP_DARK_BACKGROUND=#000000`
- **THEN** the page background is `#000000` regardless of the browser's colour-scheme preference

#### Scenario: System theme on a dark browser
- **WHEN** `APP_THEME=system`, the browser prefers dark, and `APP_DARK_BACKGROUND=#000000`
- **THEN** the page background is `#000000`

#### Scenario: Light theme active
- **WHEN** `APP_THEME=light` (or `system` on a browser that prefers light) and `APP_DARK_BACKGROUND=#000000`
- **THEN** the page looks the same as without `APP_DARK_BACKGROUND`

#### Scenario: Other surfaces unchanged
- **WHEN** `APP_DARK_BACKGROUND=#000000` and the dark theme is active
- **THEN** the sidebar, post cards, tiles, buttons and the flip view keep their existing dark colours
