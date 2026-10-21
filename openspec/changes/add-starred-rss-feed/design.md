# Design

## Context

- `store-starred-posts-in-db` adds the `starred_posts` table and the `StarredPost` model (`url`, `title`, `description` ≤ 500 chars, `published_at`, `thumbnail`, `source`, `feed` JSON with `name`/`color`/`url`/`tags`/`favicon`, `feeds` JSON, `starred_at`). This change reads that table only.
- Routes live in `routes/web.php`; `/feed` is already taken by `FeedController::get` (JSON feed info for the UI), so the new endpoint uses the requested `/favorites`.
- `TrustProxies` trusts all proxies and `X-Forwarded-Prefix`, so `url()` builds correct absolute URLs behind a sub-path proxy.
- `app.blade.php` is the single HTML shell for all Inertia pages and already sets `<base href="{{ asset('') }}">`.
- The app reads RSS with SimpleXML but has no RSS writer.

## Goals / Non-Goals

**Goals:** a standard RSS 2.0 feed that always mirrors the current star list, with no new dependency.

**Non-Goals:** auth tokens, Atom/JSON Feed, full content, caching, per-tag feeds.

## Decisions

### 1. Blade XML view, no library
`StarredFeedController::__invoke` loads `StarredPost::orderByDesc('starred_at')->get()` and returns `response()->view('starred-feed', …)->header('Content-Type', 'application/rss+xml; charset=UTF-8')`. `resources/views/starred-feed.blade.php` writes the `<?xml …?>` prolog and the RSS markup. Blade's `{{ }}` escapes `& < > " '`, which is valid XML escaping.
- Alternative: a feed library (e.g. `laminas/laminas-feed`). Rejected: one small template does not justify a dependency.
- Alternative: `DOMDocument`. Rejected: more code for the same output.

### 2. Item fields
- `title`: stored title. `link`, `guid isPermaLink="true"`: stored URL (unique per star, so readers dedupe correctly).
- `description`: stored description (already plain text, ≤ 500 chars).
- `pubDate`: `starred_at` formatted with `DATE_RSS`. Using the star time instead of the article's publish time makes newly starred older articles appear at the top of feed readers, which matches "once added it shows up in the feed".
- Thumbnail: when `thumbnail` is set and the file exists on the public disk, `<enclosure url length type>` (size and MIME type from the disk) and `<media:content url medium="image" type>`; the channel declares `xmlns:media="http://search.yahoo.com/mrss/"`. URL = `asset('storage/' . $thumbnail)`, which respects the proxy prefix. Both are used because some readers only read one of them.
- `category`: `feed.name`, when present. `source` is not used for `<source>` because RSS requires a feed URL there; it is omitted.
- Channel: `title` = `config('app.name') . ' – Starred'`, `link` = `url('starred')`, `description` = `Starred posts`, `lastBuildDate` = newest `starred_at` or now, and `<atom:link rel="self" href="{{ url('favorites') }}">` for validator compliance.

### 3. No caching
The response sets `Cache-Control: no-cache` and the query is cheap (one small table), so adds and removals are visible on the next request. No ETag; feed readers poll infrequently.

### 4. Discovery
- `app.blade.php` head: `<link rel="alternate" type="application/rss+xml" title="{{ config('app.name') }} – Starred" href="{{ url('favorites') }}">` on all pages (simplest; the head is shared).
- `Posts.vue`: in the starred view, an `RSS feed` link to `favorites` (relative, resolved via `<base>`) next to the `Starred` heading, as a plain `<a>` (not an Inertia link) so the browser opens the XML.

## Risks / Trade-offs

- [The feed is public to anyone who can reach the site] → Same exposure as the starred view; documented in the README.
- [Readers keep items after they leave the feed] → Reader behaviour; documented. The server side removal is immediate.
- [Thumbnails of pruned posts] → The feed links to the kept copies from `store-starred-posts-in-db`, which pruning does not touch. A missing file is skipped, so there are no broken enclosures.

## Migration Plan

Ships after `store-starred-posts-in-db`. No data migration. Rollback: revert the commit.

## Open Questions

None.
