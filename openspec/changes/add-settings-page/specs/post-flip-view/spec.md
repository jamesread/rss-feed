# Spec Delta

## ADDED Requirements

### Requirement: Auto-advance follows the saved setting
Flip view SHALL turn auto-advance on when it is switched on in the settings, using the saved interval. This SHALL apply on every device and browser. It SHALL NOT switch other layouts to flip view. When auto-advance is off in the settings, flip view SHALL NOT advance unless the page was opened with the `autoplay` URL parameter.

#### Scenario: Switched on in settings
- **WHEN** auto-advance is on with an interval of 15 s in the settings and a reader opens flip view on any device
- **THEN** pages advance every 15 seconds and the progress bar is shown

#### Scenario: Switched off in settings
- **WHEN** auto-advance is off in the settings and a reader opens flip view without the `autoplay` parameter
- **THEN** pages do not advance and no progress bar is shown

#### Scenario: Other layouts unaffected
- **WHEN** auto-advance is on in the settings and a reader uses the list layout
- **THEN** the list layout is shown and nothing advances

#### Scenario: Change picked up on reload
- **WHEN** auto-advance is switched off in the settings while a kiosk is cycling through pages
- **THEN** the kiosk stops advancing after its next data reload or page load

### Requirement: URL parameter takes precedence over the saved setting
The `autoplay` URL parameter SHALL turn auto-advance on for that page view with its own interval, whatever the saved setting is.

#### Scenario: URL interval wins
- **WHEN** auto-advance is on with 2 min in the settings and a browser opens `/?autoplay=45`
- **THEN** pages advance every 45 seconds

#### Scenario: URL turns it on
- **WHEN** auto-advance is off in the settings and a browser opens `/t/News?autoplay`
- **THEN** the flip layout is shown and pages advance every 30 seconds

## REMOVED Requirements

### Requirement: Auto-advance can be switched in the drawer
**Reason**: Auto-advance is now one server-side setting for the whole installation, changed on the settings page.
**Migration**: Switch auto-advance and choose its interval in Settings → General. Stored per-browser choices are ignored. Kiosk URLs with `?autoplay` keep working.
