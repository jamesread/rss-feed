# Proposal

## Why

Overlapping feeds, especially Google News searches like Acme-News, Globex-News and Initech-News, often carry the same story. It then shows up two or three times in a row on the home page and in tag views. That clutters the list and the flip mosaic.

## What Changes

- Posts in a listing that are the same story (same link, or the same title after normalization) are shown once.
- The copy shown is the newest one. It lists the names of all the feeds the story came from (e.g. `Acme-News · Globex-News`), each linking to its feed, in all layouts including flip tiles.
- Duplicates are collapsed only within the posts loaded for one page (100 posts). "Next page" still appears whenever more posts exist, even if collapsing left fewer than 100 posts on the page.
- Single-feed views (`/f/<feed>`) are unchanged in practice, since a feed rarely duplicates itself. The same rule applies there anyway.

## Capabilities

### New Capabilities
- `post-deduplication`: detecting the same story across feeds in a listing and showing it once with all its feeds.

### Modified Capabilities
<!-- none -->

## Impact

- `app/Rss/PostProvider.php`: group duplicates after loading a page, attach `feeds` (all source feeds), and report `has_more`.
- `app/Http/Controllers/PostViewController.php`: pass `hasMore` to the page.
- `resources/js/Pages/Posts.vue` and `resources/js/flip/layout.js`: use `hasMore` instead of `posts.length === 100`.
- `resources/js/Parts/Post.vue` and `FlipTile.vue`: render several feed names.
- Tests: new `PostProvider` tests, `PostViewControllerTest`, flip `layout` tests.
- Works best after `clean-aggregator-titles`, which removes the ` - Publisher` suffixes that otherwise make titles differ.
