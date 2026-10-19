# Spec Delta

## Purpose

Defines how the web UI chooses between its light and dark colour schemes and how an operator configures that choice for an instance.

## ADDED Requirements

### Requirement: Theme is configurable via APP_THEME
The application SHALL read the UI theme from the `APP_THEME` environment variable, settable in the `.env` file or as a container environment variable. It SHALL accept exactly one of `system`, `dark` or `light`. Only one theme is in effect at a time.

#### Scenario: Set in .env
- **WHEN** `.env` contains `APP_THEME=dark` and the app is served
- **THEN** every page renders with the dark theme

#### Scenario: Set as container environment variable
- **WHEN** the Docker container is started with `APP_THEME=light` in its environment
- **THEN** every page renders with the light theme

### Requirement: Default theme follows the system
When `APP_THEME` is unset or `system`, the UI SHALL use the dark theme if the browser reports a dark colour-scheme preference and the light theme otherwise, matching the behaviour before this change.

#### Scenario: Unset with dark OS preference
- **WHEN** `APP_THEME` is not set and the browser prefers a dark colour scheme
- **THEN** the UI renders with the dark theme

#### Scenario: System with light OS preference
- **WHEN** `APP_THEME=system` and the browser prefers a light colour scheme
- **THEN** the UI renders with the light theme

### Requirement: Forced dark theme
When `APP_THEME` is `dark`, the UI SHALL render with the dark theme regardless of the browser's colour-scheme preference.

#### Scenario: Dark forced on light OS
- **WHEN** `APP_THEME=dark` and the browser prefers a light colour scheme
- **THEN** the UI renders with the dark theme

### Requirement: Forced light theme
When `APP_THEME` is `light`, the UI SHALL render with the light theme regardless of the browser's colour-scheme preference.

#### Scenario: Light forced on dark OS
- **WHEN** `APP_THEME=light` and the browser prefers a dark colour scheme
- **THEN** the UI renders with the light theme

### Requirement: Invalid values fall back to system
The application SHALL treat the value case-insensitively and ignore surrounding whitespace. Any other value, including an empty string or a combination such as `dark,light`, SHALL be treated as `system` and MUST NOT cause an error page.

#### Scenario: Mixed case value
- **WHEN** `APP_THEME=Dark`
- **THEN** the UI renders with the dark theme

#### Scenario: Unknown value
- **WHEN** `APP_THEME=blue`
- **THEN** the page loads successfully and the UI follows the browser's colour-scheme preference

#### Scenario: Multiple values supplied
- **WHEN** `APP_THEME=dark,light`
- **THEN** the page loads successfully and the UI follows the browser's colour-scheme preference

### Requirement: Theme applied on first render
The configured theme SHALL be present in the initial server-rendered HTML so the page does not briefly display the other theme before scripts run.

#### Scenario: Initial HTML carries theme
- **WHEN** a page is requested with `APP_THEME=dark`
- **THEN** the returned HTML document already indicates the dark theme on its root element without relying on client-side script
