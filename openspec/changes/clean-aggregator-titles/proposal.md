# Proposal

## Why

Google News items carry the publisher in the title, e.g. `ZEK extends partnership with Initech to strengthen cyber resilience - Initech`. On flip tiles this suffix wastes the most valuable space, and the publisher is not shown where readers expect a source. The RSS item already names the publisher in its `<source>` element.

## What Changes

- When an RSS item has a `<source>` element and its title ends with ` - <source name>`, the suffix is removed from the stored title.
- The source (publisher) name is stored with the post and shown in the post's meta line next to the feed name (e.g. `Initech-News · via O'Dwyer's PR News`) in all layouts, including flip tiles.
- Items without `<source>`, and titles that do not end with the source name, are unchanged.
- Existing posts are cleaned as their feeds are refreshed (items still in the feed are updated). Older posts keep their original title.

## Capabilities

### New Capabilities
- `post-source`: recording the original publisher of aggregated posts and keeping it out of the title.

### Modified Capabilities
<!-- none -->

## Impact

- New migration: `posts.source` (string, default `''`).
- `app/Rss/RssParser.php`: read `<source>`, strip the suffix. `Post` model: fillable/property.
- `resources/js/Parts/Post.vue` and `FlipTile.vue`: show "via <source>".
- Tests: `RssParserTest`, `RefreshFeedJobTest` (update of existing post), JS snapshot or DOM assertions if present.
- Related: `collapse-duplicate-posts` matches on titles. It works better once these suffixes are removed, so implement this change first.
