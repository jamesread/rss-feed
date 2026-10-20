# Design

## Context

- Feed settings (name, color, tags, hidden) live only in `feeds.txt` (or a URL-encoded config). `RssConfig` parses them and `ConfiguredFeedProvider` wraps each `Feed` row in a `ConfiguredFeed`. The `feeds` table stores only fetch state.
- Favicon lookups run as `FetchFeedFaviconJob`, dispatched from `RefreshFeedJob` when `favicon_checked_at` is older than 7 days. `FeedFaviconFetcher::getSiteOrigin()` uses the newest post's URL origin, or the feed URL's origin.
- Refreshes start from `ConfiguredFeed::startReloading()`, either on a page view or via `rss:update-outdated-feeds` every 5 minutes. Both paths have the `ConfiguredFeed`, so the configured site is available where the job is created.
- Google News feed items link to `news.google.com/rss/articles/<id>`. These links no longer redirect over HTTP, so following them to the publisher is not an option.

## Goals / Non-Goals

**Goals:**
- Per-feed favicon site override via `feeds.txt`, applied without waiting a week.
- Never show the Google favicon for Google News feeds.
- 25% larger favicons.

**Non-Goals:**
- Guessing the company domain from a Google News search query.
- Per-post publisher icons (the item's `<source url>`).
- A UI for editing the site. It is set in `feeds.txt` like other feed settings.
- A general aggregator list. Only `news.google.com` is handled; others can be added to the same list later.

## Decisions

1. **Syntax `site=<url>` as a word on the feed line.** The parser already ignores words that are not tags, so older versions treat the token as a stray word and nothing breaks. `RssConfig::parseFromString` takes the first `site=` word whose value starts with `http://` or `https://`. `toString()` writes it right after the name, so URL-encoded configs round-trip.
   *Alternative:* a `[color|site]` suffix on the name. It is harder to read and clashes with the color syntax.

2. **Pass the site through the jobs, not through a config column.** `ConfiguredFeed` gets a `site` property and hands it to `RefreshFeedJob`, which hands it to `FetchFeedFaviconJob`, which hands it to `FeedFaviconFetcher::fetchAndStoreForFeed($feed, $site)`. The config stays the single source of truth.
   *Alternative:* sync `site` onto the `feeds` row on every page load. That is an extra write per request and duplicates config.

3. **Record the site used: new `feeds.favicon_site` column** (string, default `''`). The fetcher saves the site value it was given, including `''`. `RefreshFeedJob` treats the favicon as due when it is older than 7 days **or** `favicon_site !== $site`. This is what makes adding, changing or removing `site=` take effect at the next refresh (within about an hour, at the configured update frequency).

4. **Aggregator hosts are skipped.** The fetcher has a constant `AGGREGATOR_HOSTS = ['news.google.com']`. If there is no configured site and the derived origin's host is on that list, no request is made. Any stored favicon file is deleted, `favicon` is set to `''`, and `favicon_checked_at` is updated. The check is on the host derived from the newest post first. If the feed has no posts it falls back to the feed URL, which is also news.google.com for these feeds.

5. **Configured site → origin only.** The `site=` value is reduced to `scheme://host[:port]`, the same way post URLs are. That lets `site=https://www.example.com/insights/` work too. The existing `isSafeUrl` private-network check applies unchanged.

6. **Sizes:** Tailwind v4 accepts any spacing step. FlipTile `faviconSize` becomes `xl/l: max-h-27`, `m: max-h-18`, `s: max-h-13.5` (108/72/54 px; first +25%, then −10% after review). In grid view, `Post.vue` changes `size-24` to `size-27`. The existing `max-h-[75%]` cap stays, so on the 128 px mobile placeholder the icon still fits (96 px).

7. **Versioned favicon URL.** Icons are served with `max-age` of 7 days, and a new icon can land at the same path (`favicons/<id>.ico`). The page data therefore sends `favicons/<id>.<ext>?v=<favicon_checked_at>`. The version changes at each lookup (at most weekly), which costs one extra download per week and needs no new column.
   *Alternative:* a timestamp in the file name. It needs old-file cleanup on every lookup.

## Risks / Trade-offs

- [A feed URL used by two configs with different `site=` values (e.g. `feeds.txt` and a shared URL config) would make the favicon flip on each refresh.] → Accepted: this is a single-user app; the last refresh wins and the next due check settles it.
- [Existing Google News feeds lose their (wrong) favicon until the user adds `site=`.] → Intended. The README documents the token with a Google News example.
- [Some company sites block non-browser clients, so the lookup finds nothing.] → Same as today: the plain placeholder is shown and the lookup is retried after 7 days or when `site=` changes.

## Migration Plan

- The migration adds `favicon_site` (default `''`), and the container runs it on start. Existing rows have `''`. That matches feeds without `site=`, so no mass re-check happens. Feeds that gain a `site=` re-check at their next refresh.
- Rollback: older code ignores the column and treats `site=` as a stray word.
