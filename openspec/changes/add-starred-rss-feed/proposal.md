# Proposal

## Why

Starred posts can only be read inside the app. Publishing them as an RSS feed lets other tools pick them up, for example a feed reader on another device, a read-later service, or a newsletter or automation that consumes a curated list.

## What Changes

- New endpoint `GET /favorites` that returns an RSS 2.0 feed of all starred posts, most recently starred first.
- The feed is built from the server-side star list on every request, so a post appears as soon as it is starred and disappears as soon as it is unstarred.
- Each item carries the post's title, link, description, thumbnail image, feed name and star time. The star time is used as the item date, so a freshly starred older article shows up as new in feed readers.
- The `/starred` page links to the feed (an RSS link in the sidebar of the starred view and a `<link rel="alternate">` for auto-discovery).
- The README is updated: a Features entry and a "Starred Posts RSS Feed" section describing the `/favorites` URL, what each item contains (including the thumbnail), that the item date is the star time, that the feed is as public as the site, and that readers may keep items after they are unstarred.

Depends on `store-starred-posts-in-db`: without stars on the server there is nothing to publish, and its kept thumbnail copies are what the feed links to.

Out of scope: authentication or secret tokens for the feed (it is as open as the site itself), Atom or JSON Feed formats, full article content, per-tag starred feeds. Feed readers that already fetched an item usually keep it after it leaves the feed; that is reader behaviour and cannot be controlled by the server.

## Capabilities

### New Capabilities
- `starred-feed`: publishing the starred posts as an RSS feed.

### Modified Capabilities
<!-- none -->

## Impact

- `routes/web.php`: `GET /favorites`.
- New controller (or method) and Blade XML view for the feed, reading `StarredPost`.
- `Posts.vue`: RSS link in the starred view; `app.blade.php` or the starred page: auto-discovery link.
- Tests: feature tests for the feed output.
- Docs: README.
