# Proposal

## Why

Flip view looks like a digital newspaper, which also makes it a good information display: a headless kiosk PC or a wall screen showing the current feed. Today someone has to swipe to see more than the first page. An auto-advance mode lets the screen cycle through the pages on its own and stay current.

## What Changes

- Flip view gets an auto-advance mode. It moves to the next flip page every 30 seconds, and after the last page goes back to the first.
- When it wraps back to the first page, it reloads the posts and feeds in the background, so a kiosk that runs for days shows new posts without a manual reload.
- It can be switched on in two ways:
  - with a URL parameter, e.g. `/?autoplay` (30 s) or `/?autoplay=45` (any value from 5 to 3600 seconds). Opening such a URL also switches to the flip layout, so a fresh kiosk browser needs no clicks;
  - with an `Auto-advance` toggle in the flip view drawer, remembered in the browser.
- Auto-advance pauses while the drawer is open and for 60 seconds after someone touches, swipes, scrolls or uses the keyboard, then continues.
- A small progress bar at the bottom edge shows the time to the next page. It is hidden when auto-advance is off.
- Auto-advance applies only to flip view. Other layouts are unchanged.

## Capabilities

### New Capabilities
<!-- none -->

### Modified Capabilities
- `post-flip-view`: adds auto-advance with looping, periodic data refresh, URL and drawer activation, and pausing on interaction.

Note: `post-flip-view` is introduced by the unarchived change `add-flip-layout`. These are ADDED requirements, so the archive order does not matter.

## Impact

- `resources/js/Parts/FlipView.vue`: timer, pause logic, progress bar, drawer toggle, reload on wrap.
- `resources/js/Pages/Posts.vue`: `autoplay` URL parameter forces the flip layout.
- New `resources/js/flip/autoplay.js` for the parameter parsing and the timer and pause state.
- Tests: `npm test` for parsing and the state machine; a browser check on a kiosk-like window.
- README: a kiosk example (e.g. `chromium --kiosk "https://news.example/?autoplay"`).
