# Proposal

## Why

One extra space in `feeds.txt` (`Globex #Ecosystem  #globex`) blanked the whole production site: the tags came out of
the parser as a JSON object, and the posts page crashed while rendering. A review of the codebase turned up a few more
defects of the same kind (silent, data-dependent, and hard to diagnose from the container). Unreachable feeds in
particular fail without any trace in `docker logs`, so there is no way to tell from the outside why a feed shows no
posts.

## What Changes

- **Feed config parsing tolerates whitespace.** Any run of spaces or tabs separates the URL, name and tags in
  `feeds.txt`. Tags are always a list. Extra whitespace can no longer produce empty names or break the page.
- **Pagination keeps the active search.** Previous/next links (all layouts, including flip view) carry the search
  query, so page 2 of a search stays filtered.
- **Search submits once.** Pressing Enter in the search box triggers a single page visit, not two.
- **Unreachable feeds are handled as a failed fetch, not a crashed job.** Connection errors and timeouts are caught
  like non-2xx responses. The feed is marked as fetched, so it backs off until the next update interval and stops
  filling the `failed_jobs` table every 5 minutes.
- **Unreachable feeds are logged to the container logs.** Every failed fetch writes a warning with the feed URL and
  the reason (HTTP status or connection error). The Docker image's log stack also writes to stderr, so these
  warnings show up in `docker logs`.
- **Thumbnail fetching is hardened.** Only raster image types (jpeg, png, gif, webp, avif) are stored, so SVG
  (which can contain script) is never served from the app's origin. The private-network check also covers the post
  page request and every redirect hop.
- **Feed favicon as thumbnail fallback.** Each feed gets its website's favicon, fetched once in the background and
  stored locally. In flip view and grid view, a post without a thumbnail shows that favicon centered on its
  placeholder tile, at a size that suits the tile. Posts still without any image keep today's colored placeholder.

Out of scope (noted in the review, intentionally not changed): unused `encodeForUrl`/`decodeFromUrl`, unused
`last_accessed_at`, Atom `<link rel>` selection, Atom entries without dates, tag case sensitivity, upstream
`.forgejo/` workflows.

## Capabilities

### New Capabilities
- `feed-config`: How `feeds.txt` lines are parsed into feeds (URL, name, color, tags, hidden flag).
- `feed-refresh`: How feeds are fetched and refreshed, including failure handling and logging of unreachable feeds.
- `post-browsing`: How posts are searched and paginated in the web UI.
- `post-thumbnails`: How post thumbnail images are discovered, fetched and stored.
- `feed-favicons`: How a feed's site icon is discovered, fetched, stored and shown as the fallback for posts without a
  thumbnail.

### Modified Capabilities
<!-- none: no existing specs in openspec/specs/ -->

## Impact

- `app/Config/RssConfig.php`: line splitting and tag list.
- `app/Rss/FeedPostFetcher.php`: catch connection errors, log failures.
- `app/Rss/PostThumbnailFetcher.php`: type allow-list, safe-URL check on post URL and each redirect hop.
- `resources/js/Pages/Posts.vue`, `resources/js/Parts/FlipView.vue`: pagination data, search submit.
- `docker/.env.container`: `LOG_STACK=single,stderr`.
- Favicons: new migration adding `favicon` and `favicon_checked_at` to `feeds`, a new fetcher and queued job,
  `RefreshFeedJob` dispatch, `ConfiguredFeed` JSON (`favicon`), `resources/js/Parts/FlipTile.vue` and the grid
  placeholder in `resources/js/Parts/Post.vue`.
- Tests: `tests/Unit/RssConfigTest.php`, `tests/Feature/Jobs/RefreshFeedJobTest.php`,
  `tests/Feature/PostThumbnailFetcherTest.php`, plus a new favicon fetcher test.
- No new dependencies. One additive database migration (two columns with defaults). Existing valid `feeds.txt` files
  parse the same way.
