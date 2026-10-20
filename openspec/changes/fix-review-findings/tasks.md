# Tasks

## 1. Feed config whitespace

- [x] 1.1 Add failing cases to `tests/Unit/RssConfigTest.php`: double space between tags, double space between URL and name, tab separators, non-tag word between tags. Assert name, `getTags()` equals the expected list, and `array_is_list(getTags())`. Verify `php artisan test --filter=RssConfigTest` fails on the new cases
- [x] 1.2 In `app/Config/RssConfig.php::parseFromString`, split with `preg_split('/\s+/', $line)` and wrap the tag filter in `array_values()`. Verify `php artisan test --filter=RssConfigTest` passes
- [x] 1.3 Add a README note under "Feed Configuration" that fields may be separated by any spaces or tabs. Verify the README example block still matches the parser behavior

## 2. Unreachable feeds: handling and logging

- [x] 2.1 Add failing tests to `tests/Feature/Jobs/RefreshFeedJobTest.php`. (a) `Http::fake` throws `ConnectionException`: the job completes, no posts, `last_fetched_at` updated. (b) 404 response: `Log::warning` called with the URL and `HTTP 404`. (c) connection error: warning with the URL and the error message. (d) 200 response: no warning. Use `Log::spy()`. Verify the new tests fail
- [x] 2.2 In `app/Rss/FeedPostFetcher.php`, catch `ConnectionException` and return `[]`, and log `Log::warning('Feed fetch failed', ['url' => …, 'reason' => …])` for non-2xx and connection errors. Verify `php artisan test --filter=RefreshFeedJobTest` passes
- [x] 2.3 Add `LOG_STACK=single,stderr` to `docker/.env.container` and document it in the README docker environment section. Verify `grep LOG_STACK docker/.env.container` and that the README lists it

## 3. Thumbnail fetcher hardening

- [x] 3.1 Add failing tests to `tests/Feature/PostThumbnailFetcherTest.php`: an SVG image is rejected (no file, no thumbnail), a post URL on a private IP is not requested (`Http::assertNothingSent`), and a redirect to a private target is not followed while a redirect to a public host is (Http::fake runs Guzzle's redirect middleware, so this is tested end-to-end). Verify the new tests fail
- [x] 3.2 In `app/Rss/PostThumbnailFetcher.php`, replace MIME-subtype extensions with an allow-list map (jpeg, png, gif, webp, avif; JPEG keeps its existing `.jpeg` extension) and reject everything else. Verify the SVG test passes and the existing JPEG/PNG tests still pass
- [x] 3.3 Call `isSafeUrl($post->url)` before fetching the post page, and configure both requests with `allow_redirects` (`max` 5, `on_redirect` calling a protected check method that throws on unsafe hops). Catch that exception as a failed fetch. Verify `php artisan test --filter=PostThumbnailFetcherTest` passes

## 4. Search and pagination UI

- [x] 4.1 Add a `pageData(page)` method to `resources/js/Pages/Posts.vue` returning `{page}` plus `query` when `search` is set, and use it for the Previous/Next links. Verify in the browser: search, then Next page keeps `?query=…&page=2` and filtered results; without a search the URL is `?page=2`
- [x] 4.2 Add the same `pageData(page)` to `resources/js/Parts/FlipView.vue` and use it for the empty-page link and the next/prev tiles. Verify in flip view with a search that the next-page tile keeps the query
- [x] 4.3 Remove `@change="submitSearch"` from the search input in `Posts.vue`. Verify in browser devtools (Network) that Enter triggers exactly one request

## 5. Feed favicon fallback

- [x] 5.1 Add a migration for `feeds.favicon` (string, default `''`) and `feeds.favicon_checked_at` (integer, default `0`), and update the `Feed` model docblock. Verify `php artisan migrate:fresh` and the existing test suite pass
- [x] 5.2 Make `PostThumbnailFetcher`'s download method take the allowed MIME → extension map (thumbnail map unchanged). Verify `php artisan test --filter=PostThumbnailFetcherTest` still passes
- [x] 5.3 Add `tests/Feature/FeedFaviconFetcherTest.php` with `Http::fake` covering: largest declared icon wins (apple-touch-icon 180 over 32px icon), fallback to `/favicon.ico`, SVG rejected then `/favicon.ico` tried, lookup host taken from the newest post URL (feed URL origin when there are no posts), ICO stored as `favicons/{id}.ico`, and `favicon_checked_at` set on both success and failure. Verify the tests fail
- [x] 5.4 Implement `app/Rss/FeedFaviconFetcher.php` and `app/Jobs/FetchFeedFaviconJob.php` (`ShouldBeUnique` per feed) as described in design decisions 9–11. Verify `php artisan test --filter=FeedFaviconFetcherTest` passes
- [x] 5.5 Dispatch `FetchFeedFaviconJob` from `RefreshFeedJob` when `app.load_post_thumbnails` is on and `favicon_checked_at` is older than 7 days. Add `RefreshFeedJobTest` cases using `Queue::fake()`: dispatched when due, not dispatched when checked 2 days ago, not dispatched when thumbnails are disabled. Verify `php artisan test --filter=RefreshFeedJobTest` passes
- [x] 5.6 Add `favicon` to `ConfiguredFeed::jsonSerialize` and assert it in `tests/Feature/PostViewControllerTest.php` (empty string by default, path when set). Verify the test passes
- [x] 5.7 In `resources/js/Parts/FlipTile.vue`, change the placeholder branch to title / centered favicon / feed row, with the favicon at `size-12` (s), `size-16` (m) or `size-24` (l, xl), `object-contain` on a `bg-white/90` rounded backdrop, `alt=""`, and hidden on `@error`. Verify in flip view (desktop + iPhone emulation, light and dark) that the icon is centered, doesn't overlap the title on small tiles, and tiles with thumbnails are unchanged
- [x] 5.8 In the grid placeholder of `resources/js/Parts/Post.vue`, center a `size-24` favicon with the same styling above the title. Verify in grid view that posts without a thumbnail show the icon, feeds without a favicon keep the plain colored block, and card/list/compact views are unchanged
- [x] 5.9 Document in the README (under the `APP_LOAD_POST_THUMBNAILS` setting) that it also controls feed favicon fetching. Verify the README text matches the behavior

## 6. Integration checks

- [x] 6.1 Run `php artisan test`, `vendor/bin/phpstan analyse`, `npm test` and `npm run build`, and verify all pass
- [x] 6.2 Build and run the image (`docker compose -f docker-compose.testing.yml up --build`) with a `feeds.txt` containing a double-spaced line and an unreachable feed URL. Verify the page renders, `docker logs` shows the `Feed fetch failed` warning with that URL, and `php artisan queue:failed` lists nothing for it. After a refresh, posts without thumbnails show their feed's favicon in flip and grid view

## Workflow follow-up

- Archive the change with `/opsx:archive` after review and deployment.
