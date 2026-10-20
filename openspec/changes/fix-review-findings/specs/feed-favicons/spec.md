# Spec Delta

## Purpose

Defines how the application finds, fetches and stores the website icon for each feed, and how that icon is shown in place of a missing post thumbnail.

## ADDED Requirements

### Requirement: Feed favicon is fetched from the feed's website
For each feed, the system SHALL find the site icon of the website its posts link to (the origin of the newest post's URL, or the feed URL's origin if the feed has no posts). It SHALL prefer the largest icon declared in the page's `<link rel="…icon">` tags and fall back to `/favicon.ico`.

#### Scenario: Declared icons
- **WHEN** the site's home page declares a 32×32 `icon` and a 180×180 `apple-touch-icon`
- **THEN** the 180×180 icon is stored as the feed's favicon

#### Scenario: No declared icon
- **WHEN** the site's home page declares no icon and `/favicon.ico` returns an image
- **THEN** `/favicon.ico` is stored as the feed's favicon

#### Scenario: Feed served from a different host than its articles
- **WHEN** a feed URL is on `feeds.example-cdn.com` and its newest post links to `https://www.example.com/article`
- **THEN** the favicon is looked up on `https://www.example.com`

### Requirement: Favicons are stored locally and fetched safely
Favicons SHALL be stored by the application and served from its own storage, so readers' browsers never contact third-party favicon services. Favicon requests SHALL follow the same private network and redirect checks as thumbnail requests. Only PNG, JPEG, GIF, WebP and ICO images up to 1 MB SHALL be stored. SVG SHALL be rejected.

#### Scenario: SVG icon rejected
- **WHEN** the only declared icon is an SVG file
- **THEN** no SVG is stored, and `/favicon.ico` is tried instead

#### Scenario: Private address
- **WHEN** the feed's website resolves to a private network address
- **THEN** no favicon request is made

### Requirement: Favicon lookups are limited
The system SHALL look up a feed's favicon at most once every 7 days, whether or not the lookup succeeded. It SHALL only look up favicons when post thumbnail loading is enabled (`APP_LOAD_POST_THUMBNAILS`).

#### Scenario: Recent failed lookup
- **WHEN** a feed's favicon lookup found no icon 2 days ago and the feed is refreshed
- **THEN** no new favicon lookup is made

#### Scenario: Thumbnails disabled
- **WHEN** `APP_LOAD_POST_THUMBNAILS=false` and a feed is refreshed
- **THEN** no favicon lookup is made

### Requirement: Favicon replaces the missing thumbnail on placeholder tiles
In flip view and grid view, a post without a thumbnail whose feed has a favicon SHALL show the favicon centered on its placeholder tile. The post title and feed name SHALL stay readable. Posts with a thumbnail, and the card, list and compact views, SHALL be unchanged.

#### Scenario: Flip view tile without a thumbnail
- **WHEN** a post without a thumbnail from a feed with a favicon appears in flip view
- **THEN** the tile shows the feed's favicon in its center on the placeholder background, along with the post title and feed name

#### Scenario: Feed without a favicon
- **WHEN** a post has no thumbnail and its feed has no favicon
- **THEN** the tile shows today's colored placeholder

#### Scenario: Post with a thumbnail
- **WHEN** a post has a thumbnail
- **THEN** the thumbnail is shown and the favicon is not

### Requirement: Favicon size suits the tile
The favicon SHALL be displayed at a size that grows with the tile: up to 48 px on small tiles, 64 px on medium tiles, and 96 px on large and extra-large flip tiles and grid tiles. It SHALL shrink to fit the space between the title and the feed name, keep a square aspect ratio, and sit on a light rounded backdrop so dark icons stay visible.

#### Scenario: Large flip tile
- **WHEN** a post without a thumbnail is shown on an extra-large flip tile
- **THEN** its feed favicon is shown at 96 × 96 px, centered

#### Scenario: Short tile
- **WHEN** a large tile is too short for a 96 px icon between the title and the feed name (for example on a phone in landscape)
- **THEN** the icon shrinks to the available height, stays square and does not overlap the title

#### Scenario: Small flip tile
- **WHEN** a post without a thumbnail is shown on a small flip tile
- **THEN** its feed favicon is shown at 48 × 48 px and does not overlap the title text
