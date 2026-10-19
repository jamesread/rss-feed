# Tasks

## 1. Decouple the header row from the per-view width

- [x] 1.1 In `resources/js/Pages/Posts.vue`, move the header row (`<div class="flex items-center justify-between mb-2">` containing the "Posts" heading and `<FormatSelector>`) out of the width-constrained wrapper (`:class="{'max-w-lg': …, 'max-w-2xl': …, 'w-full': …}"`) so it is a direct child of the posts column and spans its full width; leave the post list, pagination and their per-view width classes unchanged. Verify `npm run build` succeeds.
- [ ] 1.2 Manually verify in a browser (`npm run dev` + `php artisan serve`) at desktop width: clicking card → grid → list → compact leaves the selector at the same screen position (e.g. compare `getBoundingClientRect()` of the selector in devtools after each click), its right edge aligns with the posts column's right padding, and the card/list views still show their narrow/medium post widths.
- [ ] 1.3 Manually verify at mobile width (devtools device mode): the selector stays top-right and does not move when switching views, the "Toggle filters & options" button still works, and the header row scrolls away with the list (no sticky behaviour).

## 2. Integration checks

- [x] 2.1 Run `php artisan test` and verify the suite still passes (the Inertia page props are unchanged).
