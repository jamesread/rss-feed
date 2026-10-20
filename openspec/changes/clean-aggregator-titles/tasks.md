# Tasks

## 1. Parse and store the source

- [ ] 1.1 Migration adding `posts.source` (string 250, default `''`); add to `Post` `$fillable` and `@property`. Verify: `php artisan test` passes after `migrate:fresh`.
- [ ] 1.2 `RssParser`: read `<source>`, set `source`, strip a final ` - <source>` from the title (entity-decoded, exact match). Verify: `RssParserTest` cases for the Initech item, the O'Dwyer's item with several dashes, a non-matching title, and no `<source>`.
- [ ] 1.3 Refresh updates existing posts. Verify: `RefreshFeedJobTest` stores a post with the suffixed title, refreshes with a `<source>` item of the same guid, and asserts the cleaned title and the source.

## 2. Show the source

- [ ] 2.1 `Post.vue` (card/list/compact/grid) and `FlipTile.vue`: `via <source>` after the feed name, truncating before the feed name and time. Verify: `npm run build` passes; in the browser a Google News post shows `via <publisher>` in card and flip views, and a long source truncates on a small flip tile.

## 3. Verification

- [ ] 3.1 Gates: `php artisan test`, `vendor/bin/phpstan --memory-limit=1G`, `npm test`, `npm run build`. All pass.
- [ ] 3.2 Test container (not `rss-flip`/8081) with the Initech Google News feed. Verify: titles have no ` - Publisher` suffix and tiles show `via <publisher>`.
