# Spec Delta

## ADDED Requirements

### Requirement: Saved theme overrides APP_THEME
A theme saved on the settings page SHALL take precedence over `APP_THEME`. Without a saved theme, `APP_THEME` SHALL apply as before. The saved theme SHALL be present in the initial server-rendered HTML, like the environment theme.

#### Scenario: Saved theme wins
- **WHEN** `APP_THEME=dark` is set and `Light` was saved on the settings page
- **THEN** every page renders with the light theme

#### Scenario: Nothing saved
- **WHEN** no theme was saved and `APP_THEME=dark` is set
- **THEN** every page renders with the dark theme

#### Scenario: System saved
- **WHEN** `System` was saved and the browser prefers a dark colour scheme
- **THEN** the UI renders with the dark theme, regardless of `APP_THEME`

### Requirement: Saved dark background overrides APP_DARK_BACKGROUND
A dark background colour saved on the settings page SHALL take precedence over `APP_DARK_BACKGROUND` for the dark page background and every dark surface derived from it. Without a saved colour, `APP_DARK_BACKGROUND` and then the built-in default SHALL apply as before.

#### Scenario: Saved colour wins
- **WHEN** `APP_DARK_BACKGROUND=#101828` is set and `#000000` was saved on the settings page
- **THEN** pages shown with the dark theme have the background `#000000` and surfaces derived from it

#### Scenario: Saved colour removed
- **WHEN** the saved colour is reset to the default and `APP_DARK_BACKGROUND` is unset
- **THEN** pages shown with the dark theme use the built-in default background
