# Design

## Context

- `Post` hides `id` in JSON, so the browser sees posts by `url` (plus title, description, `published_at`, `thumbnail`, and the attached `feed`).
- The layout choice is already kept in `localStorage` (`post-format`), so per-browser state is an established pattern.
- `Posts.vue` renders all layouts and passes `posts` to `FlipView`. The sidebar is a single block that flip view teleports into its drawer.

## Goals / Non-Goals

**Goals:** a one-tap read-later list that survives pruning and needs no server state.

**Non-Goals:** sync across devices, import/export, read/unread state, starring from search results on other pages (it works anywhere a post is shown).

## Decisions

1. **Storage module `resources/js/starred.js`:** `getStarred()`, `isStarred(url)`, `toggleStar(post)`, `onStarredChange(cb)`. Key `starred-posts`; the value is a JSON array of snapshots `{url, title, description, published_at, thumbnail, starred_at, feed: {name, color, url}}`. Every access is wrapped in try/catch. A module-level reactive copy (Vue `reactive`) keeps all star buttons and the count in sync without reloads. A `storage` event listener syncs other tabs.
   *Alternative:* server table. Rejected by the per-browser choice.
2. **Thumbnails in snapshots:** the thumbnail path is stored, and the existing `@error` handling falls back to the placeholder once the file is pruned.
3. **Route `/starred`:** server renders `Posts` with `feeds` (for the sidebar), `posts: []`, `starred: true`, `hasMore`/paging disabled. On the client, `Posts.vue` uses `starred ? starredPosts : posts`. The snapshots carry `feed`, so the existing post components render them unchanged.
4. **StarButton.vue:** an outlined/filled star SVG button with `@click.stop.prevent`. In `FlipTile` it is placed top-right with `relative z-10`, above the full-tile link overlay (`after:absolute after:inset-0`), so tapping it does not open the post.
5. **Sidebar link:** `Starred (n)` below the tag list, styled like the tag links, marked `data-flip-keep-drawer` so the flip drawer behaves like other filters.

## Risks / Trade-offs

- [Local storage is about 5 MB per origin.] → Snapshots are about 1 KB each; descriptions are capped at 500 characters in the snapshot, so thousands of stars fit.
- [Clearing site data loses stars.] → Documented in the README as a property of per-browser stars.
