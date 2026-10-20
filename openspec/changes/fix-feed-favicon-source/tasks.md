# Tasks

## 1. `site=` token in feed config

- [x] 1.1 `RssConfig`: parse the first `site=http(s)://…` word into a `site` field (ignore invalid values, never a tag), add `getSite()`, and write `site=…` after the name in `toString()`. Verify: new `RssConfigTest` cases for the Acme line, missing token, `site=example.com`, and the `encodeForUrl`/`decodeFromUrl` round trip pass.
- [x] 1.2 `ConfiguredFeed` gets a `site` property, filled by `ConfiguredFeedProvider`, and not added to page JSON. Verify: `ConfiguredFeedTest` asserts the site is set from the config.
- [x] 1.3 README `feeds.txt` section documents `site=` with a Google News example. Verify: the README example line parses as documented (covered by 1.1's Acme case).

## 2. Favicon lookup uses the configured site

- [x] 2.1 Migration adding `feeds.favicon_site` (string, default `''`); add the `@property` to `Feed`. Verify: `php artisan migrate:fresh` in the test DB succeeds and `php artisan test` passes.
- [x] 2.2 `FeedFaviconFetcher::fetchAndStoreForFeed(Feed $feed, string $site = '')`: use the configured site's origin when given, skip `news.google.com` (delete the stored file, clear `favicon`), and save `favicon_site`. Verify: `FeedFaviconFetcherTest` cases for configured site with a path (looks up the origin only), Google News post links without site (no HTTP request made, `Http::assertNothingSent`), and a previously stored favicon removed.
- [x] 2.3 Pass the site `ConfiguredFeed::startReloading` → `RefreshFeedJob` → `FetchFeedFaviconJob` → fetcher. `RefreshFeedJob` dispatches when the check is over 7 days old **or** `favicon_site !== $site`. Verify: `RefreshFeedJobTest` cases where a site was added 1 day after the last check (dispatches with the site) and the site is unchanged 2 days after (no dispatch). Existing tests still pass.

## 3. Larger favicons

- [x] 3.1 `FlipTile.vue` `faviconSize`: `xl/l: max-h-30`, `m: max-h-20`, `s: max-h-15`. `Post.vue` grid: `size-24` → `size-30`, keep `max-h-[75%]`. Verify: `npm run build` succeeds, and in the browser an xl flip tile icon measures 120 px, a small one 60 px, and the grid icon 120 px on desktop without overlapping the title.

## 4. Verification

- [x] 4.1 Full gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm run build`. All pass.
- [x] 4.2 End to end in a test container (not `rss-flip`/8081) with a `feeds.txt` containing the Acme and Initech Google News feeds with `site=` and one without. Verify: after a refresh, the Acme and Initech tiles show the company icons and the feed without `site=` shows the plain placeholder.

## Workflow follow-up

- Archive `fix-review-findings` before archiving this change, since this change's `MODIFIED` deltas target requirements it adds.
- After deploying, add `site=` to the Google News feeds in the server's `feeds.txt`.
