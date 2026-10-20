# Spec Delta

## Purpose

Defines how readers search and page through posts in the web UI across all layouts, including the flip view.

## ADDED Requirements

### Requirement: Pagination preserves the search query
When a search is active, links to the previous and next page SHALL keep the search query, in every post layout including the flip view.

#### Scenario: Next page of a search
- **WHEN** the reader searches for `kubernetes` and follows the "Next page" link
- **THEN** page 2 shows only posts matching `kubernetes` and the search box still shows `kubernetes`

#### Scenario: Flip view page tiles
- **WHEN** the reader is in flip view with an active search and selects the next-page or previous-page tile
- **THEN** the loaded page keeps the search query

#### Scenario: No search active
- **WHEN** no search is active and the reader follows a pagination link
- **THEN** the URL contains only the page number and no empty query parameter

### Requirement: Search submits once
Submitting the search box SHALL trigger exactly one page visit.

#### Scenario: Press Enter
- **WHEN** the reader types a term in the search box and presses Enter
- **THEN** exactly one request for the search results is made
