# Tasks

## 1. Group duplicates on the server

- [x] 1.1 `PostProvider::getLatest()` loads `count + 1` posts, returns the first `count` collapsed by URL or normalized title (≥ 20 chars), each kept post with `feeds` (all distinct feeds, newest first), plus a `hasMore` flag. Verify: new `PostProviderTest` cases for same URL, punctuation-only title difference, short identical titles not merged, three-feed order, and `hasMore` true/false.
- [x] 1.2 `PostViewController` passes `hasMore` to `Posts`. Verify: `PostViewControllerTest` asserts `hasMore` and a collapsed post's `feeds` in page data.

## 2. Show it in the UI

- [x] 2.1 `Posts.vue` and `flip/layout.js` `buildPages()` use `hasMore` for the next-page control. Verify: `npm test` layout cases where 94 posts plus `hasMore: true` yield a next tile, and 100 posts plus `hasMore: false` yield none.
- [x] 2.2 `FeedNames` component (names joined by ` · `, each linking to its feed, truncating) used in `Post.vue` and `FlipTile.vue`. Verify: `npm run build` passes; in the browser a story present in two Google News feeds shows once with both names in card and flip views, and truncates on a small tile.

## 3. Verification

- [x] 3.1 Gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm test`, `npm run build`. All pass.
- [x] 3.2 Test container (not `rss-flip`/8081) with the Acme, Globex and Initech Google News feeds tagged `#Ecosystem`. Verify: `/t/Ecosystem` shows shared stories once with all feed names.
