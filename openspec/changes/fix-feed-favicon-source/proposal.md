# Proposal

## Why

Google News search feeds (for example `Acme-News`, `Initech-News` and `Globex-News`) all show the Google favicon. Their post links point to `news.google.com/rss/articles/…`, so the favicon lookup finds news.google.com as the feed's website. A search query cannot be mapped to the right company site reliably, so the reader needs a way to set the site per feed. Separately, the favicon is too small on placeholder tiles and should be 25% larger.

## What Changes

- New optional `site=<url>` token per feed line in `feeds.txt`, for example
  `https://news.google.com/rss/search?q=Acme… Acme-News site=https://www.example.com #acme`.
  When set, the favicon is looked up on that site instead of the site the posts link to.
- Without a `site=` token, feeds whose posts link to `news.google.com` get no favicon. They show the plain colored placeholder, not the misleading Google icon.
- When a feed's `site=` value changes (or is added or removed), its favicon is looked up again at the next refresh instead of waiting for the 7-day limit.
- Favicons on placeholder tiles are 25% larger: 60 px on small, 80 px on medium, and 120 px on large and extra-large flip tiles and grid tiles. They still shrink to fit short tiles.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `feed-config`: feed lines accept an optional `site=<url>` token, which is kept when the config is written back or encoded in a URL.
- `feed-favicons`: lookups use the configured site when there is one, skip aggregator links (news.google.com), and run again when the configured site changes. Display sizes grow by 25%.

Note: both capabilities are introduced by the unarchived change `fix-review-findings`. Archive that change before this one so these deltas apply to its specs.

## Impact

- `app/Config/RssConfig.php`, `ConfiguredFeed.php` and `ConfiguredFeedProvider.php`: parse, store, serialize and pass on the `site` value.
- `app/Jobs/RefreshFeedJob.php`, `FetchFeedFaviconJob.php` and `app/Rss/FeedFaviconFetcher.php`: favicon origin choice and the re-check rule.
- New migration: `favicon_site` column on `feeds`, recording which configured site the stored favicon came from.
- `resources/js/Parts/FlipTile.vue` and `Post.vue`: larger icon size classes.
- `README.md`: document the `site=` token.
- Tests: `RssConfigTest`, `FeedFaviconFetcherTest`, `RefreshFeedJobTest`.
- Existing deployments: Google News feeds lose their current Google favicon at the next favicon check unless a `site=` is configured.
