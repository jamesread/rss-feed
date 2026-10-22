# Spec Delta

## ADDED Requirements

### Requirement: Social Media menu below Feeds
The settings page's left pane SHALL list a `Social Media` menu directly below `Feeds`. Selecting it SHALL show the AI settings on the right, mark `Social Media` as selected, and give it its own URL (`/settings/ai`) so a reload shows it again.

#### Scenario: Menu order
- **WHEN** a reader opens `/settings`
- **THEN** the left pane lists `General`, `Feeds` and `Social Media`, in that order

#### Scenario: Open the Social Media menu
- **WHEN** a reader selects `Social Media` and then reloads the page
- **THEN** the AI settings are shown and `Social Media` is marked as selected
