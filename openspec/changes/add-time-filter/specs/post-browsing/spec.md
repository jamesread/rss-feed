# Spec Delta

## ADDED Requirements

### Requirement: Posts can be filtered by age
Post listings (home, tag and feed views) SHALL accept a `since` parameter with the values `24h`, `7d` and `30d`. When it is set, only posts published within that period before the request SHALL be listed. A missing or unknown value SHALL list all posts.

#### Scenario: Last 24 hours
- **WHEN** a reader opens `/?since=24h`
- **THEN** only posts published in the last 24 hours are listed

#### Scenario: Unknown value
- **WHEN** a reader opens `/?since=1y`
- **THEN** all posts are listed, as without the parameter

#### Scenario: Combined with search
- **WHEN** a reader opens `/t/Ecosystem?since=7d&query=partnership`
- **THEN** only posts tagged `#Ecosystem` from the last 7 days whose title or description contains `partnership` are listed

### Requirement: Time filter control
The sidebar SHALL show the options `All`, `24 h`, `7 days` and `30 days`, with the active one highlighted. Choosing one SHALL reload the current view with that filter, keep any search query, and start at page 1. The control SHALL also be available in the flip view drawer.

#### Scenario: Choose 7 days
- **WHEN** a reader on `/t/Ecosystem` chooses `7 days`
- **THEN** the page reloads as `/t/Ecosystem?since=7d` on page 1 and `7 days` is highlighted

#### Scenario: Back to all
- **WHEN** a reader with `since=24h` chooses `All`
- **THEN** the `since` parameter is removed and all posts are listed

### Requirement: Time filter is kept while browsing
Next/previous page controls (including flip view page tiles) and search submissions SHALL keep the active time filter. Following a tag or feed link SHALL NOT carry it over.

#### Scenario: Paging
- **WHEN** a reader on `/?since=30d` follows `Next Page`
- **THEN** the next page is `/?page=2&since=30d`

#### Scenario: Search
- **WHEN** a reader with `since=24h` submits a search
- **THEN** the search results are also limited to the last 24 hours

#### Scenario: Flip view label
- **WHEN** flip view shows `/t/Ecosystem?since=24h`
- **THEN** the menu button reads `Posts / #Ecosystem · 24 h`
