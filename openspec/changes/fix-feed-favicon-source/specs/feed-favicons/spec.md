# Spec Delta

## MODIFIED Requirements

### Requirement: Feed favicon is fetched from the feed's website
For each feed, the system SHALL find the site icon of the feed's website. If the feed line sets `site=<url>`, that site's origin is used. Otherwise the website is the origin of the newest post's URL, or the feed URL's origin if the feed has no posts. It SHALL prefer the largest icon declared in the page's `<link rel="…icon">` tags and fall back to `/favicon.ico`.

#### Scenario: Declared icons
- **WHEN** the site's home page declares a 32×32 `icon` and a 180×180 `apple-touch-icon`
- **THEN** the 180×180 icon is stored as the feed's favicon

#### Scenario: No declared icon
- **WHEN** the site's home page declares no icon and `/favicon.ico` returns an image
- **THEN** `/favicon.ico` is stored as the feed's favicon

#### Scenario: Feed served from a different host than its articles
- **WHEN** a feed URL is on `feeds.example-cdn.com` and its newest post links to `https://www.example.com/article`
- **THEN** the favicon is looked up on `https://www.example.com`

#### Scenario: Configured site
- **WHEN** a Google News search feed for Acme is configured with `site=https://www.example.com/insights/`
- **THEN** the favicon is looked up on `https://www.example.com`, not on news.google.com

### Requirement: Favicon size suits the tile
The favicon SHALL be displayed at a size that grows with the tile: up to 60 px on small tiles, 80 px on medium tiles, and 120 px on large and extra-large flip tiles and grid tiles. It SHALL shrink to fit the space between the title and the feed name, keep a square aspect ratio, and sit on a light rounded backdrop so dark icons stay visible.

#### Scenario: Large flip tile
- **WHEN** a post without a thumbnail is shown on an extra-large flip tile
- **THEN** its feed favicon is shown at 120 × 120 px, centered

#### Scenario: Short tile
- **WHEN** a large tile is too short for a 120 px icon between the title and the feed name (for example on a phone in landscape)
- **THEN** the icon shrinks to the available height, stays square and does not overlap the title

#### Scenario: Small flip tile
- **WHEN** a post without a thumbnail is shown on a small flip tile
- **THEN** its feed favicon is shown at 60 × 60 px and does not overlap the title text

#### Scenario: Grid tile
- **WHEN** a post without a thumbnail is shown in grid view on a wide screen
- **THEN** its feed favicon is shown at up to 120 × 120 px and stays within the placeholder

## ADDED Requirements

### Requirement: Aggregator links do not give a favicon
Without a configured site, the system SHALL NOT use news.google.com as a feed's website. Such feeds SHALL have no favicon and show the plain placeholder. A Google favicon stored earlier SHALL be removed at the next favicon check.

#### Scenario: Google News feed without site
- **WHEN** a feed's posts link to `https://news.google.com/rss/articles/…` and the feed line has no `site=` token
- **THEN** no favicon lookup is made, the feed has no favicon, and its tiles show the colored placeholder

#### Scenario: Previously stored Google favicon
- **WHEN** a Google News feed without `site=` already has the Google favicon stored and its favicon check is due
- **THEN** the stored favicon is removed

### Requirement: Changing the configured site triggers a new lookup
When a feed's configured site differs from the site its last favicon lookup used (including adding or removing `site=`), the next refresh of that feed SHALL look up the favicon again, regardless of the 7-day limit.

#### Scenario: Site added to an existing feed
- **WHEN** `site=https://www.example.org` is added to the Initech-News feed, whose favicon was checked 1 day ago
- **THEN** the next refresh of that feed looks up the favicon on `https://www.example.org`

#### Scenario: Site unchanged
- **WHEN** a feed's `site=` is unchanged and its favicon was checked 2 days ago
- **THEN** no new favicon lookup is made at the next refresh
