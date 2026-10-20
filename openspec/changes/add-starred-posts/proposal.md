# Proposal

## Why

There is no way to keep a post for later. Posts scroll away as feeds update, and old posts are pruned. Readers need a simple read-later list. The app has no user accounts, so the list belongs to the browser.

## What Changes

- Every post gets a star button in all layouts (card, list, compact, grid, flip tile). Clicking it stars or unstars the post.
- Starred posts are saved in the browser, including a copy of the details needed to show them (title, link, description, time, feed name, color and URL). They therefore survive post pruning and feed changes.
- A "Starred" entry in the sidebar (and so also in the flip drawer) opens a list of starred posts, newest starred first, in the current layout. From there they can be unstarred.
- Stars are per browser and do not sync between devices.

## Capabilities

### New Capabilities
- `starred-posts`: starring posts, keeping them in the browser, and listing them.

### Modified Capabilities
<!-- none -->

## Impact

- `routes/web.php` and `PostViewController`: a `/starred` route that renders the posts page with the feeds and no server posts.
- New `resources/js/starred.js` (storage helpers) and a `StarButton.vue` part.
- `Posts.vue`, `Post.vue`, `FlipTile.vue`, `FlipView.vue`: star buttons, the starred view, and the sidebar link.
- Tests: `PostViewControllerTest` for the route; `npm test` for the storage helpers.
