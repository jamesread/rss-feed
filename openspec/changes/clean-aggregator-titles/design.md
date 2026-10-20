# Design

## Context

- `RssParser::getPostDataForRssItem()` builds `title`, `description`, `url`, `guid` and `published_at`. Atom items do not have `<source>` in this form and are out of scope.
- `RefreshFeedJob` calls `updateOrCreate(['guid' => …], $post->getAttributes())`, so existing posts already get updated attributes at each refresh. Adding `source` to the attributes is enough for the backfill.
- Google News always writes `<title>Headline - Publisher</title>` plus `<source url="…">Publisher</source>`.

## Goals / Non-Goals

**Goals:** shorter, cleaner titles for aggregator items, and a visible publisher.

**Non-Goals:** guessing publishers for feeds without `<source>`; heuristic stripping of `- Something` suffixes; rewriting posts that are no longer in the feed.

## Decisions

1. **Strip only an exact, final ` - <source>` match** (compared after trimming and HTML-entity decoding both sides, case-sensitive). This is safe for titles that contain dashes, and for publishers whose name appears elsewhere in the title.
   *Alternative:* strip anything after the last ` - `. That breaks titles like `Markets rally - analysis`.
2. **Generic on `<source>`, not Google-specific.** `<source>` is standard RSS 2.0, and other aggregators use the same pattern.
3. **`posts.source` column** (string 250, default `''`), added to `$fillable` and exposed in page data (it is not in `$hidden`).
4. **UI:** in `Post.vue` and `FlipTile.vue`, after the feed name link add `<span class="truncate min-w-0">via {{ post.source }}</span>` when present. In `FlipTile` the feed-name row is already a `flex items-center gap-2 min-w-0` row, so the source truncates first.

## Risks / Trade-offs

- [Titles are capped at 250 characters before stripping, so a cut-off suffix won't match.] → Rare; such titles are left unchanged.
- [Changing the title of an existing post changes what search matches.] → Intended. The publisher is no longer part of the title search, which reduces noise.
