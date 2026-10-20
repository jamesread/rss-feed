# Tasks

## 1. Record fetch outcome

- [x] 1.1 Migration adding `feeds.last_fetch_error` (string, default `''`) and `feeds.last_fetch_succeeded_at` (int, default 0); add `@property` lines to `Feed`. Verify: `php artisan test` passes after `migrate:fresh`.
- [x] 1.2 `FeedPostFetcher::fetchForFeed()` returns a `FeedFetchResult` (posts + error). Connection errors, non-2xx responses and XML parse errors (`Invalid feed content`) set the error; a valid feed with no items has none. Verify: new `FeedPostFetcher` tests for 404, connection failure, HTML body, empty valid RSS.
- [x] 1.3 `RefreshFeedJob` stores `last_fetch_error` / `last_fetch_succeeded_at` from the result (success clears the error). Verify: `RefreshFeedJobTest` cases for failure keeping the old success time, and recovery clearing the error.

## 2. Show the marker

- [x] 2.1 `ConfiguredFeed::jsonSerialize()` adds `fetch_error` and `fetch_succeeded_at`. Verify: `PostViewControllerTest` asserts both fields for a failing and a healthy feed.
- [x] 2.2 `Feed.vue`: red dot right of the name, vertically centered, `title`/`aria-label` built from reason + relative last-success time (or "never"). Verify: `npm test` case for the label text; in the browser a failing feed shows the dot centered on the name in the sidebar and in the flip drawer, and the dot does not overlap a wrapped name.

## 3. Verification

- [x] 3.1 Gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm test`, `npm run build`. All pass.
- [x] 3.2 Test container (not `rss-flip`/8081) with one valid and one 404 feed in `feeds.txt`. Verify: after a refresh, only the 404 feed has the dot, and its tooltip shows `HTTP 404`.
