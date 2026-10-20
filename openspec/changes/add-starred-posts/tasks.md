# Tasks

## 1. Storage

- [ ] 1.1 `resources/js/starred.js`: snapshot storage keyed by URL (description capped at 500 chars), reactive state, cross-tab `storage` sync, try/catch around all storage access. Verify: `npm test` cases for toggle on/off, order by `starred_at`, pruned-post snapshot rendering data, and a throwing `localStorage`.

## 2. Star controls

- [ ] 2.1 `StarButton.vue` (outlined/filled, `Star post`/`Unstar post` label, `click.stop.prevent`), added to `Post.vue` (all four layouts) and `FlipTile.vue` (top-right, above the tile link). Verify: `npm run build` passes; in the browser starring in card view fills the star, and tapping the star on a flip tile does not open the post.

## 3. Starred view

- [ ] 3.1 `/starred` route in `routes/web.php` + `PostViewController::starred()` rendering `Posts` with `feeds`, `posts: []`, `starred: true`. Verify: `PostViewControllerTest` asserts the route renders with `starred` true and no posts.
- [ ] 3.2 `Posts.vue`: use starred snapshots when `starred`, empty-state text, hide server paging; `FlipView`/`buildPages` without next/prev tiles in that mode; sidebar `Starred (n)` link with `data-flip-keep-drawer`. Verify: `npm test` layout case for starred mode; in the browser `/starred` lists starred posts newest-starred first in card and flip views, and unstarring removes the entry and updates the count.
- [ ] 3.3 README: add starred posts to Features, noting stars are per browser. Verify: README renders the new bullet.

## 4. Verification

- [ ] 4.1 Gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm test`, `npm run build`. All pass.
- [ ] 4.2 Test container (not `rss-flip`/8081): star 3 posts, reload, open `/starred` in card and flip view, unstar one. Verify: the list and count match after each step, and a private window loads without script errors.
