# Design

## Context

- `FeedPostFetcher::fetchForFeed()` returns `[]` on connection errors and non-2xx responses, and logs a warning. `RssParser::rssDataToPosts()` swallows XML exceptions and also returns `[]`. So callers cannot tell a failure from an empty feed.
- `RefreshFeedJob` always sets `last_fetched_at` after a fetch, success or not. `last_fetched_at` controls when a feed is outdated, and must keep doing that, so that failing feeds are not retried on every page view.
- The sidebar is `resources/js/Parts/Feed.vue`. Flip view teleports the same sidebar into its drawer, so one change covers both.

## Goals / Non-Goals

**Goals:** a visible, accurate "last fetch failed" marker with the reason.

**Non-Goals:** fetch history, notifications, retry/backoff changes, or a status page.

## Decisions

1. **Return a result object from the fetcher.** `fetchForFeed()` returns a small value object `FeedFetchResult { Post[] posts; ?string error }` instead of a bare array. Parse errors are detected by calling `parseRssDataToPosts()` (which throws) and catching the error in the fetcher, with the reason `Invalid feed content`. The existing warning log stays.
   *Alternative:* an exception for failures. That would need try/catch in the job and changes the "failed fetch is not a failed job" behavior from `fix-review-findings`.
2. **Two columns on `feeds`:** `last_fetch_error` (string, default `''`) and `last_fetch_succeeded_at` (int, default `0`). The error is truncated to 250 characters. A non-empty error means "failing".
3. **Page data:** `ConfiguredFeed::jsonSerialize()` adds `fetch_error` (string) and `fetch_succeeded_at` (int). No new endpoint.
4. **Marker markup:** inside the `<h4>`, wrap the name link and the dot in an inline-flex `items-center gap-1.5` container. The dot is `size-2 rounded-full bg-red-600 shrink-0` with `title` and `aria-label` set to e.g. `Last fetch failed: HTTP 404 · last success 2 days ago`. The text reuses the existing `timestampToRelativeTime` helper. `items-center` centers the dot on the name's line. When the name wraps, the dot sits after the last line, since it is inline after the text and `self-end` is not used.

## Risks / Trade-offs

- [A feed fails once due to a short outage, and the dot shows for up to an hour, until the next refresh.] → Acceptable: it reflects the truth of the last attempt. The tooltip shows the last success, which tells a brief blip from a long-standing problem.
- [Tooltips do not exist on touch devices.] → The accessible label carries the text. Long-press shows the `title` in some mobile browsers only. A tap target is out of scope.

## Migration Plan

The migration adds both columns with defaults, so existing feeds show no dot until their next fetch. Rollback: older code ignores the columns.
