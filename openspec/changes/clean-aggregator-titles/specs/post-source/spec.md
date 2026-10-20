# Spec Delta

## Purpose

Records the original publisher of posts that come from aggregator feeds such as Google News, shows it as the post's source, and keeps the publisher name out of the post title.

## ADDED Requirements

### Requirement: Publisher suffix is removed from titles
When an RSS item has a `<source>` element with a non-empty name and the item's title ends with ` - ` followed by exactly that name, the stored post title SHALL be the title without that suffix. Otherwise the title SHALL be stored unchanged.

#### Scenario: Google News item
- **WHEN** an item's title is `ZEK extends partnership with Initech to strengthen cyber resilience - Initech` and its source is `Initech`
- **THEN** the post title is `ZEK extends partnership with Initech to strengthen cyber resilience`

#### Scenario: Several dashes in the title
- **WHEN** an item's title is `PR News | FGS Global Works DC for Initech - Mon., Oct. 19, 2026 - O'Dwyer's PR News` and its source is `O'Dwyer's PR News`
- **THEN** only the last suffix is removed: `PR News | FGS Global Works DC for Initech - Mon., Oct. 19, 2026`

#### Scenario: Title does not end with the source
- **WHEN** an item's source is `Reuters` and its title is `Markets rally - analysis`
- **THEN** the title is stored unchanged

#### Scenario: No source element
- **WHEN** an item has no `<source>` element
- **THEN** the title is stored unchanged and the post has no source

### Requirement: Post source is stored and shown
The publisher name from `<source>` SHALL be stored with the post. Where a post shows its feed name (card, list, compact, grid and flip views), a post with a source SHALL also show `via <source>` next to the feed name. A post without a source SHALL look as it does today.

#### Scenario: Flip tile with source
- **WHEN** a post from the Initech-News feed has the source `O'Dwyer's PR News`
- **THEN** its tile shows `Initech-News` and `via O'Dwyer's PR News`

#### Scenario: Long source name on a small tile
- **WHEN** a small flip tile shows a post with a long source name
- **THEN** the source is truncated with an ellipsis and does not push the feed name or time off the tile

### Requirement: Refreshed posts get the cleaned title
When a feed refresh returns an item that is already stored, its stored title and source SHALL be updated to the cleaned values.

#### Scenario: Existing Google News post
- **WHEN** a post was stored with the title suffix before this change and the item is still in the feed at the next refresh
- **THEN** the post's title loses the suffix and its source is set
