# Proposal

## Why

Failed feed fetches are only written to the container logs. A broken feed (wrong URL, site down, invalid XML) goes unnoticed until someone wonders why it has no new posts. The sidebar should show at a glance which feeds are failing.

## What Changes

- Each feed records the outcome of its last fetch: when it last succeeded and, if the last fetch failed, when and why.
- Invalid feed content (XML that cannot be parsed) counts as a failed fetch. Today it is silently treated as an empty feed.
- In the sidebar (and so also in the flip view drawer, which shows the same sidebar), a small red dot appears right of a failing feed's name, vertically centered on the name text. Hovering or long-pressing it shows the reason and the time of the last successful fetch.
- The dot disappears after the next successful fetch.

## Capabilities

### New Capabilities
- `feed-status`: recording each feed's last fetch outcome and showing failing feeds in the UI.

### Modified Capabilities
<!-- none -->

## Impact

- New migration: `last_fetch_error` (string) and `last_fetch_succeeded_at` (int) on `feeds`.
- `app/Rss/FeedPostFetcher.php` and `RssParser.php`: report failures, including parse errors.
- `app/Jobs/RefreshFeedJob.php`: store the outcome.
- `app/Config/ConfiguredFeed.php`: add the status to page data.
- `resources/js/Parts/Feed.vue`: red dot with tooltip.
- Tests: `RefreshFeedJobTest`, `PostViewControllerTest`, and a JS test if the tooltip text is formatted in a helper.
