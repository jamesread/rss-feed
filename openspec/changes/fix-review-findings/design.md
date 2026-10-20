# Design

## Context

See proposal.md (Why) and the four specs for required behavior. Relevant current state:

- `RssConfig::parseFromString` splits each line with `explode(' ', …)` and filters tags with `array_filter`, which
  keeps the original keys. A gap in those keys makes PHP serialize the tags as a JSON object. `Posts.vue` then fails on
  `for (const tag of feed.tags)`, and Vue renders nothing.
- `FeedPostFetcher::fetchForFeed` returns `[]` for non-2xx responses, but `Http::get` throws `ConnectionException` on
  timeouts and DNS errors. That exception fails `RefreshFeedJob` (worker runs `--tries=3`). `last_fetched_at` is never
  set, so the scheduler (`everyFiveMinutes`) and every page view dispatch the job again.
- Logging uses `LOG_CHANNEL=stack` with `LOG_STACK=single`, so entries only go to `storage/logs/laravel.log`. In the
  container, supervisord runs the queue worker with `redirect_stderr=true` and stdout set to `/dev/stdout`. The
  `stderr` channel in `config/logging.php` is already defined.
- `PostThumbnailFetcher` checks `isSafeUrl()` only for the image URL. The extension is taken from the sniffed MIME
  subtype (`svg+xml` included), and Laravel's HTTP client follows redirects by default.
- Pagination links in `Posts.vue` and `FlipView.vue` pass `:data="{page: …}"` to `pagePath` (the bare pathname).
- Posts without a thumbnail render a colored placeholder: `FlipTile.vue` (all sizes) and the grid branch of `Post.vue`.
  Posts carry their feed as `post.feed` (a serialized `ConfiguredFeed`), so feed-level data reaches every tile without
  extra queries. Thumbnails are stored on the `public` disk under `thumbs/` and served as `storage/…`.

## Goals / Non-Goals

**Goals:**
- Fix each defect with the smallest change in the file that owns it, keeping upstream rebases easy.
- Cover each backend fix with a PHPUnit test that fails before the fix.

**Non-Goals:**
- No retry/backoff policy beyond the existing update interval. No per-feed error state in the UI or database.
- No change to how valid `feeds.txt` lines parse today.
- No general SSRF framework. Only the thumbnail fetcher is in scope (feed URLs come from the operator).

## Decisions

1. **Split config lines with `preg_split('/\s+/', $line)` and wrap the tag filter in `array_values()`.**
   `preg_split` fixes the root cause (empty parts) for spaces and tabs. `array_values` guarantees a list even when a
   non-tag word sits between tags. Alternative: only `array_values`. Rejected, because a double space between URL and
   name would still produce an empty name.

2. **Catch `ConnectionException` in `FeedPostFetcher` and log there.** The fetcher already owns the "non-2xx → no
   posts" decision, so both failure kinds (status and connection) end up in one place with one log call:
   `Log::warning('Feed fetch failed', ['url' => $feed->url, 'reason' => 'HTTP 404' | $e->getMessage()])`.
   `RefreshFeedJob` stays unchanged and sets `last_fetched_at` as it does today. Alternative: catch in the job and use
   `$this->fail()`. Rejected, because that still records failed jobs and doesn't set `last_fetched_at`.

3. **Route logs to the container by adding `LOG_STACK=single,stderr` to `docker/.env.container`.** This only changes
   the Docker image's defaults. Local `.env` setups keep file-only logging, and operators can still override
   `LOG_STACK`. The queue worker's stderr already reaches `docker logs` via supervisord. Alternative: a dedicated log
   channel used only for feed errors. Rejected as extra config for one message type.

4. **Thumbnail type allow-list: map `image/jpeg|png|gif|webp|avif` to an extension and reject everything else.**
   Explicit mapping also fixes odd extensions (`svg+xml`, `x-icon`). Alternative: keep sniffing and serve thumbnails
   with `Content-Security-Policy: sandbox`. Rejected as it needs Caddy changes and still stores untrusted SVG.

5. **Check every redirect hop instead of disabling redirects.** Aggregator links (Google News, feedproxy) in the
   operator's feeds redirect to the real article, so turning redirects off would lose their thumbnails. Use the
   client's Guzzle option `allow_redirects` with `max` 5 and an `on_redirect` callback that throws when
   `isSafeUrl($uri)` fails. Catch that exception like a failed fetch. Also check `$post->url` before the first request.

6. **Pagination data via a small `pageData(page)` method in `Posts.vue` and `FlipView.vue`** that returns
   `{page}` plus `query` only when `search` is non-empty, so URLs without a search stay `?page=N`. Duplicating
   the 3-line method in two components is simpler than a shared helper (FlipView receives `search` as a prop already).

7. **Remove `@change="submitSearch"` from the search input.** Enter triggers the form's `@submit.prevent`. Blur no
   longer searches. That matches typical search boxes and avoids the double visit.

8. **Favicon per feed, stored on the `feeds` table.** Add `favicon` (string, default `''`, storage path) and
   `favicon_checked_at` (integer, default `0`) in a new migration. Per feed rather than per post: one icon covers every
   post of a feed, and lookups stay at roughly one per feed per week. Alternative: per post host. Rejected, because
   it's many more requests, and aggregator feeds would still mostly resolve to the aggregator.

9. **Look up the icon on the origin of the newest post URL, falling back to the feed URL origin.** This fixes feeds
   served from CDNs or proxies (`feeds.arstechnica.com`, `feedproxy.feedly.com`, `raw.githubusercontent.com`) whose
   host isn't the site. Parse `<link rel>` values containing `icon` (including `apple-touch-icon`) from the first
   ~1 MB of the home page, using the same regex approach as the `og:image` lookup. Pick the largest `sizes` value
   (apple-touch-icon without `sizes` counts as 180). Fall back to `/favicon.ico`.

10. **Share the safe download path with thumbnails.** Group 3 adds the URL/redirect checks to `PostThumbnailFetcher`.
    The new `FeedFaviconFetcher` reuses them by calling `PostThumbnailFetcher`'s download method, which takes the
    allowed MIME → extension map as a parameter (thumbnails: jpeg/png/gif/webp/avif, favicons: those minus avif plus
    `x-icon`/`vnd.microsoft.icon` → `ico`). ICO is a raster format and browsers don't run scripts in it, so it's safe
    to serve. SVG stays rejected for the same reason as thumbnails. Store at `favicons/{feed_id}.{ext}` on the public
    disk.

11. **Queue a `FetchFeedFaviconJob` from `RefreshFeedJob`** after posts are stored, when `app.load_post_thumbnails` is
    on and `favicon_checked_at` is older than 7 days. The job sets `favicon_checked_at = time()` whether or not it
    succeeds. `ShouldBeUnique` per feed id, like the other jobs. This reuses the existing thumbnail flag instead of
    adding a new env variable.

12. **Frontend: show `post.feed.favicon` in the placeholder, centered, sized per tile.** Add `favicon` to
    `ConfiguredFeed::jsonSerialize`. In `FlipTile.vue`'s placeholder branch, change the layout to three rows: title,
    a `flex-1` centered favicon, and feed name/time. The icon is `size-12` (s), `size-16` (m), `size-24` (l, xl). In the
    `Post.vue` grid placeholder, center a `size-24` icon above the title. The icon is an `<img>` with `object-contain`
    inside a `bg-white/90 rounded-xl p-2` backdrop, `alt=""` (decorative, the feed name is already shown) and
    `loading="lazy"`. If the image fails to load (`@error`), hide it so the old placeholder shows.

## Risks / Trade-offs

- [`isSafeUrl` resolves DNS in tests] → tests use public hostnames as the existing tests do. `Http::fake` still
  runs Guzzle's redirect middleware, so redirect checks are tested end-to-end with faked 302 responses.
- [Dead feeds now produce one warning per update interval, every hour by default] → acceptable and intended. It's the
  signal the operator asked for. Not deduplicated.
- [Removing blur-to-search changes behavior for anyone relying on it] → minor, and Enter still works on mobile
  keyboards.
- [Stricter thumbnail types drop SVG/ICO thumbnails that used to display] → those fall back to the existing
  no-thumbnail rendering.
- [Sites that only have a 16 px `favicon.ico` look soft when scaled to 48–96 px] → the largest declared icon is
  preferred, which avoids it for most sites. Rendering pixelated would look worse for real photos/logos.
- [Aggregator feeds (Google News) get the aggregator's icon, not each article's site] → accepted. A per-post lookup is
  out of scope.
- [Favicon files of removed feeds stay on disk] → they're tiny (≤ 1 MB, usually a few KB), and post pruning doesn't
  touch feeds. Not handled in this change.

## Migration Plan

One additive migration (`feeds.favicon`, `feeds.favicon_checked_at`, both with defaults), which runs on container start
via `artisan migrate --force`. Favicons fill in over the next feed refreshes. Deploy the new image as usual. Existing `failed_jobs` rows from dead feeds can be cleared once
with `php artisan queue:flush`. Rollback is redeploying the previous image.
