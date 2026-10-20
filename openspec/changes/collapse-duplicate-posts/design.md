# Design

## Context

- `PostProvider::getLatest()` loads up to 100 posts ordered by `published_at desc` and attaches each post's `ConfiguredFeed` as `feed`.
- The UI decides "more posts exist" from `posts.length === 100` (`Posts.vue`, and `resources/js/flip/layout.js` via `POSTS_PER_SERVER_PAGE`). That breaks as soon as a page holds fewer than 100 posts after collapsing.
- SQLite is the database; titles and URLs are not normalized in storage.

## Goals / Non-Goals

**Goals:** one entry per story per page, attributed to all its feeds, and correct paging.

**Non-Goals:** collapsing across page boundaries; fuzzy or semantic matching; persisting groups in the database; following redirects to compare final URLs.

## Decisions

1. **Collapse in PHP after the query.** Load `101` posts (one extra only to know whether more exist), then keep the first 100 and group them in memory with a union-find over two keys: URL and normalized title (≥ 20 chars). In-memory work on 100 rows is trivial and needs no schema change.
   *Alternative:* a stored `title_hash` column and SQL grouping. That is more complex, and it cannot express "URL or title".
2. **Payload shape:** each kept post keeps `feed` (its own feed, unchanged for existing code) and gains `feeds`, a list of `{name, url, color}` for all distinct feeds in the group, newest first. Single posts get `feeds` with one entry. This keeps all current consumers working.
3. **`hasMore` page prop** (bool, `true` when the 101st row exists). `Posts.vue` and `buildPages()` take it instead of comparing lengths. `POSTS_PER_SERVER_PAGE` stays only for any other uses.
4. **Rendering:** a small shared `FeedNames` component renders `feeds` joined by ` · `, each a `Link` to `f/<url>` in its color, inside a `truncate min-w-0` container. It is used in `Post.vue` and `FlipTile.vue` where `post.feed.name` is rendered now.
5. **Favicon on placeholder tiles** keeps using the shown (newest) post's own feed.

## Risks / Trade-offs

- [A story can still appear twice when its copies fall on different pages.] → Accepted; the newest copies of a story are usually close together in time.
- [Two different stories with the same long headline are merged.] → Unlikely with a 20-character minimum. Both feeds are still named, so nothing is hidden silently.
- [A page can contain noticeably fewer than 100 entries in duplicate-heavy tag views.] → Acceptable; the next-page control stays correct.
