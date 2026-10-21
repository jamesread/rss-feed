# Tasks

## 1. Feed endpoint

- [x] 1.1 `GET /favorites` route, `StarredFeedController` and `resources/views/starred-feed.blade.php` per design.md decisions 1–3. Verify: new `tests/Feature/StarredFeedTest.php` covers content type `application/rss+xml`, item order by `starred_at` desc, item fields (`title`, `link`, `guid`, `description`, `category`, `pubDate` `Wed, 21 Oct 2026 08:00:00 +0000`), an empty list giving a channel with no items, a title with `&`, `<` and `"` parsing with `simplexml_load_string` back to the original text, a removed star disappearing on the next request, and channel/self links using the `X-Forwarded-Prefix` path
- [x] 1.2 Thumbnail elements per design.md decision 2. Verify: `StarredFeedTest` with a fake public disk asserts `enclosure` (absolute URL, `image/jpeg`, byte length) and `media:content` for a post with a kept thumbnail, and neither element for a post without one or with a missing file
- [x] 1.3 Parse the generated feed with the app's own `RssParser` in a test. Verify: the parser returns the starred posts with their titles and URLs (round trip proves the feed is readable by an RSS consumer)

## 2. Discovery

- [x] 2.1 `app.blade.php`: alternate link to `favorites` in the head; `Posts.vue`: `RSS feed` link in the starred view per decision 4. Verify: `PostViewControllerTest` asserts the alternate link in the `/starred` HTML; `npm run build` and `npm test` pass

## 3. Documentation

- [x] 3.1 README: add a Features entry and a "Starred Posts RSS Feed" section with the `/favorites` URL, the item contents (title, link, description, thumbnail, feed name), the star time as item date, that the feed is as public as the site, and that readers may keep items after unstarring. Link it from the "Starred Posts" section added by `store-starred-posts-in-db`. Verify: `grep -n favorites README.md` shows the Features entry and the section

## 4. Integration checks

- [x] 4.1 Run `php artisan test`, `vendor/bin/phpstan analyse --memory-limit=1G` and `npm test`, and verify all pass
- [x] 4.2 Against `php artisan serve` with a scratch SQLite DB (or a test container not named `rss-flip`/`rss-local`, not on 8081/8082): star two posts in the UI (one with a thumbnail), `curl /favorites` shows both newest first and the thumbnail URL in the item returns the image with `curl -I` (200, `image/*`); unstar one, `curl` again shows only the other; the feed validates with `xmllint --noout`. Clean up afterwards

## Workflow follow-up

- Implement after `store-starred-posts-in-db`.
- After deploying, subscribe to https://news.local.michard.cc/favorites in a feed reader.
