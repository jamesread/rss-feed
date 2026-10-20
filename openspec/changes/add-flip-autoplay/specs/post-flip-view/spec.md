# Spec Delta

## ADDED Requirements

### Requirement: Flip view can advance pages automatically
When auto-advance is on, flip view SHALL move to the next flip page after each interval (30 seconds by default). After the last flip page it SHALL go back to the first. It SHALL NOT follow the next/previous server page tiles. Page changes SHALL use the same transition as a manual page turn.

#### Scenario: Regular advance
- **WHEN** auto-advance is on with the default interval and flip page 2 of 5 is shown
- **THEN** after 30 seconds flip page 3 is shown

#### Scenario: Loop
- **WHEN** auto-advance is on and the last flip page has been shown for one interval
- **THEN** the first flip page is shown

#### Scenario: Single page
- **WHEN** auto-advance is on and the posts fit on one flip page
- **THEN** the page stays, and the data refresh still happens once per interval

### Requirement: Data is refreshed on each loop
Each time auto-advance wraps back to the first flip page, flip view SHALL reload the current view's posts and feeds without a full page reload, keeping the current filter, search and time filter. If the reload fails, it SHALL keep showing the existing posts and try again on the next loop.

#### Scenario: New posts appear
- **WHEN** a new post was fetched while the kiosk was cycling through the pages
- **THEN** it is shown after the next wrap to the first page

#### Scenario: Server unreachable
- **WHEN** the reload at the wrap fails because the server is unreachable
- **THEN** the existing posts stay visible and auto-advance continues

### Requirement: Auto-advance can be switched on by URL
Opening any posts view with the `autoplay` parameter SHALL switch to the flip layout and turn auto-advance on. `autoplay` without a value or with an invalid value SHALL use 30 seconds; a whole number from 5 to 3600 SHALL set the interval in seconds. The parameter SHALL be kept when the reload at a wrap happens.

#### Scenario: Kiosk boot
- **WHEN** a fresh browser with no saved settings opens `/t/Ecosystem?autoplay`
- **THEN** the flip layout is shown for `#Ecosystem` and pages advance every 30 seconds

#### Scenario: Custom interval
- **WHEN** a browser opens `/?autoplay=45`
- **THEN** pages advance every 45 seconds

#### Scenario: Out of range
- **WHEN** a browser opens `/?autoplay=2`
- **THEN** pages advance every 30 seconds

### Requirement: Auto-advance can be switched in the drawer
The flip view drawer SHALL contain an `Auto-advance` toggle that shows whether auto-advance is on. Turning it on or off SHALL take effect immediately and SHALL be remembered in the browser for later visits. When auto-advance was turned on by URL, the toggle SHALL show it as on, and turning it off SHALL stop it for the current page view.

#### Scenario: Turn on by hand
- **WHEN** a reader turns on `Auto-advance` in the drawer and closes it
- **THEN** pages advance every 30 seconds, also after reloading the page

#### Scenario: Turn off
- **WHEN** a reader turns `Auto-advance` off
- **THEN** pages stop advancing and the progress bar disappears

### Requirement: Auto-advance pauses on interaction
Auto-advance SHALL pause while the drawer is open. It SHALL also pause for 60 seconds after the last touch, swipe, wheel or keyboard input in flip view, then resume with a full interval. It SHALL pause while the browser tab is hidden.

#### Scenario: Reader swipes
- **WHEN** auto-advance is on and a reader swipes to another page
- **THEN** no automatic page turn happens until 60 seconds after the last input, then the full interval runs again

#### Scenario: Drawer open
- **WHEN** the drawer is open
- **THEN** pages do not advance until it is closed

### Requirement: Progress to the next page is visible
While auto-advance is on, flip view SHALL show a thin progress bar along the bottom edge (inside the safe area). It fills over the interval and resets on each page change. It SHALL show no progress while paused. It SHALL not cover tile text or the page indicator, and SHALL work in light and dark themes.

#### Scenario: Running
- **WHEN** auto-advance is on and not paused
- **THEN** the bar fills from empty to full over the interval and resets when the page changes

#### Scenario: Off
- **WHEN** auto-advance is off
- **THEN** no progress bar is shown
