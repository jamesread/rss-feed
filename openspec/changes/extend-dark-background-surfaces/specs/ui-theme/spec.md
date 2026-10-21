# Spec Delta

## ADDED Requirements

### Requirement: Dark surfaces follow the configured dark background
When a valid `APP_DARK_BACKGROUND` is set and the dark theme is active, every dark-theme background, border and divider in the list, card, grid, compact and flip views SHALL use the configured colour or a shade derived from it, instead of the default Tailwind grays. This includes the page, sidebar, post hover state, controls and flip view. No other environment variable is needed.

#### Scenario: Post hover follows the colour
- **WHEN** `APP_DARK_BACKGROUND="#006b6b"`, the dark theme is active, and the pointer hovers over a post
- **THEN** the post's hover background and border are teal shades derived from `#006b6b`, not slate gray

#### Scenario: Sidebar follows the colour
- **WHEN** `APP_DARK_BACKGROUND="#006b6b"` and the dark theme is active
- **THEN** the left sidebar background is a teal shade derived from `#006b6b`, and the search input, active filter and active format button use derived shades too

#### Scenario: Flip view follows the colour
- **WHEN** `APP_DARK_BACKGROUND="#006b6b"`, the dark theme is active, and the flip view is open
- **THEN** the flip page background, menu drawer, navigation buttons and tile placeholders use the colour or derived shades, not slate gray

### Requirement: Derived shades keep the existing contrast steps
Derived shades SHALL keep the same light/dark ordering as the default palette. The sidebar and hover surfaces SHALL be lighter than the page, borders and active controls lighter than those surfaces, and the flip page darker than the main page. The configured colour SHALL be used unchanged as the main page background.

#### Scenario: Ordering of shades
- **WHEN** `APP_DARK_BACKGROUND="#006b6b"` and the dark theme is active
- **THEN** the main page background is exactly `#006b6b`, the sidebar is lighter than it, the post hover border is lighter than the sidebar, and the flip page background is darker than `#006b6b`

### Requirement: Default dark surfaces are unchanged
When `APP_DARK_BACKGROUND` is unset, empty or invalid, every dark-theme surface SHALL render with exactly the same colour as before this change.

#### Scenario: Unset
- **WHEN** `APP_DARK_BACKGROUND` is not set and the dark theme is active
- **THEN** the page, sidebar, post hover, controls and flip view render with the same computed colours as before this change

### Requirement: Configured dark background does not affect light theme, text or content colours
The configured colour SHALL have no visible effect while the light theme is active. It SHALL NOT change text colours, black divider lines, feed colours or post images.

#### Scenario: Light theme active
- **WHEN** `APP_THEME=light` (or `system` on a browser that prefers light) and `APP_DARK_BACKGROUND="#006b6b"`
- **THEN** the page looks the same as without `APP_DARK_BACKGROUND`

#### Scenario: Text and feed colours unchanged
- **WHEN** `APP_DARK_BACKGROUND="#006b6b"` and the dark theme is active
- **THEN** text colours and feed-coloured flip tiles are the same as without `APP_DARK_BACKGROUND`

## REMOVED Requirements

### Requirement: Dark page background applies only to the dark theme's page background
**Reason**: In use, recolouring only the page left the sidebar, hover states and flip view in the default slate gray, which looked unfinished. Replaced by "Dark surfaces follow the configured dark background" and "Configured dark background does not affect light theme, text or content colours".
**Migration**: None needed for configuration. Instances that set `APP_DARK_BACKGROUND` will see the sidebar, hover states, controls and flip view take on shades of the configured colour.
