# Spec Delta

## Purpose

Defines how the posts page lets the user switch between post views and where the view selector is placed so it can be used predictably.

## ADDED Requirements

### Requirement: View selector has a stable position
The view selector SHALL be shown at the top-right of the posts column and its horizontal and vertical position SHALL be the same for every view (card, grid, list, compact) at a given viewport size.

#### Scenario: Switching views does not move the selector
- **WHEN** the user is on the posts page with the card view and clicks the list, grid and compact buttons in turn
- **THEN** the view selector stays at the same screen position after each click

#### Scenario: Aligned to the posts column edge
- **WHEN** the posts page is shown in any view on a desktop-width viewport
- **THEN** the right edge of the view selector lines up with the right content edge of the posts column, not with the edge of the post list

#### Scenario: Narrow viewport
- **WHEN** the posts page is shown on a mobile-width viewport and the user switches views
- **THEN** the view selector stays at the top-right of the posts area and does not move

### Requirement: Post list width still follows the selected view
Switching views SHALL keep changing the width of the post list as before (card narrowest, list wider, grid and compact full column width), independently of the selector's position.

#### Scenario: Card view stays narrow
- **WHEN** the user selects the card view on a wide viewport
- **THEN** posts are shown in the narrow card column while the view selector remains at the top-right of the posts column

### Requirement: Selector scrolls with the content
The view selector SHALL scroll together with the post list; it is not pinned to the viewport while scrolling.

#### Scenario: Scrolling down
- **WHEN** the user scrolls down the post list
- **THEN** the header row with the heading and the view selector scrolls out of view
