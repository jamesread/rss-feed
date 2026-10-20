# Proposal

## Why

Listings always go back as far as the stored history. To see what happened today or this week, readers have to page and scan the timestamps. A simple time filter narrows any view to recent posts.

## What Changes

- The sidebar gets a time filter with four options: `All`, `24 h`, `7 days`, `30 days`. `All` is the default.
- The choice is a URL parameter (`since=24h|7d|30d`). It applies to the home, tag and feed views, and combines with search.
- Paging, the search form, and the flip view's page tiles keep the time filter. Following a tag or feed link starts that view unfiltered, matching how search behaves today.
- In flip view the filter is reachable through the drawer, and the menu button's label shows the active filter (e.g. `Posts / #Ecosystem · 24 h`).

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `post-browsing`: adds the time filter and its interaction with search and paging.

Note: `post-browsing` is introduced by the unarchived change `fix-review-findings`. These are ADDED requirements, so the archive order does not matter.

## Impact

- `app/Http/Controllers/PostViewController.php`: read and validate `since`, add a `published_at >=` condition, pass `since` to the page.
- `resources/js/Pages/Posts.vue` and `FlipView.vue`: filter control, `pageData()` and search keeping `since`, flip menu label.
- Tests: `PostViewControllerTest`; JS test if `pageData` is extracted.
