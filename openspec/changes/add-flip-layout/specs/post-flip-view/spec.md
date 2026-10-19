# Spec Delta

## Purpose

Defines the flip post view: a full-screen, page-by-page mosaic of post tiles for reading on tablets and phones. Covers how tiles are laid out, how the user moves between pages, and how the view keeps the filters, search, pagination and other posts-page behaviour of the existing views.

## ADDED Requirements

### Requirement: Flip is a selectable view
The view selector SHALL offer a fifth view, "flip", next to card, grid, list and compact. Selecting it SHALL switch the posts page to the flip view without a page reload.

#### Scenario: Selecting flip
- **WHEN** the user is on the posts page in card view and clicks the flip button in the view selector
- **THEN** the posts are shown as the flip mosaic without the browser reloading the page

#### Scenario: Flip button marked active
- **WHEN** the flip view is active and the user opens the view selector
- **THEN** the flip button is shown as the selected view, the same way the active button is marked for the other views

### Requirement: Selected view persists across reloads
The flip choice SHALL be persisted in the same way as the other views and restored on reload and on navigation to other posts pages, in the same browser.

#### Scenario: Reload keeps flip
- **WHEN** the user selects flip and reloads the page
- **THEN** the posts page is shown in flip view again

#### Scenario: Leaving flip persists the new choice
- **WHEN** the user is in flip view, selects list and reloads the page
- **THEN** the posts page is shown in list view

#### Scenario: Navigation keeps flip
- **WHEN** the user is in flip view and opens a tag or feed filter
- **THEN** the filtered posts page is shown in flip view

### Requirement: Full-viewport mosaic
In flip view the post mosaic SHALL fill the whole viewport with no page margins or sidebar column. Tiles and controls SHALL stay inside the device safe areas, while the page background extends to the screen edges.

#### Scenario: iPad landscape
- **WHEN** flip view is shown on an iPad in landscape
- **THEN** the mosaic spans the full width and height of the screen and no sidebar column is visible

#### Scenario: iPhone with notch in landscape
- **WHEN** flip view is shown on an iPhone with a notch in landscape
- **THEN** no tile content, text or control is hidden under the notch or the home indicator

### Requirement: Other views respect safe areas
The card, grid, list and compact views SHALL keep their content inside the device safe areas once the page is allowed to extend under the notch.

#### Scenario: List view on notched phone
- **WHEN** list view is shown on an iPhone with a notch in landscape
- **THEN** no text or control is hidden under the notch or the home indicator

### Requirement: Posts shown as tiles
Each post SHALL be shown as one rectangular tile with the post title and the feed name over it. Tiles on the same screen page SHALL have different sizes, chosen from a set of page templates.

#### Scenario: Tile with thumbnail
- **WHEN** a post has a thumbnail
- **THEN** its tile is filled with the thumbnail and shows the post title and feed name legibly on top of it

#### Scenario: Varied sizes
- **WHEN** a screen page shows several posts
- **THEN** at least two tiles on that page differ in size

### Requirement: Placeholder for posts without thumbnail
A post without a thumbnail SHALL be shown as a tile in its feed colour with its title as text, following the grid view placeholder, with text size suited to the tile size.

#### Scenario: Large tile without thumbnail
- **WHEN** a post without a thumbnail is placed in a large tile
- **THEN** the tile shows the feed-colour background and the title in large text without overflowing the tile

#### Scenario: Small tile without thumbnail
- **WHEN** a post without a thumbnail is placed in the smallest tile size
- **THEN** the title is shortened to fit the tile and the full title is available as the tile's tooltip

### Requirement: Thumbnails get the larger tiles
Within one screen page, posts with a thumbnail SHALL be placed in the largest tiles before posts without one. Posts SHALL NOT move between screen pages because of this; screen pages keep the server's newest-first order.

#### Scenario: Mixed page
- **WHEN** a screen page holds two posts with thumbnails and four without, and its template has one large and five smaller slots
- **THEN** a post with a thumbnail occupies the large slot

#### Scenario: Page order preserved
- **WHEN** posts A to Z (newest first) are split into screen pages
- **THEN** every post on screen page 1 is newer than or as new as every post on screen page 2

### Requirement: Layout varies between pages
Consecutive screen pages SHALL use different page templates, so the mosaic does not repeat the same arrangement on every page.

#### Scenario: Swiping through pages
- **WHEN** the user moves from one full screen page to the next
- **THEN** the next page uses a different tile arrangement than the previous one

### Requirement: Layout is stable
For the same posts, server page and device class, the flip view SHALL produce the same pages and tile arrangement every time.

#### Scenario: Reload
- **WHEN** the user reloads the page on the same device and orientation and the posts have not changed
- **THEN** every screen page shows the same posts in the same tiles as before

### Requirement: Tile density follows device class
The number of tiles per screen page SHALL depend on the viewport, with fewer tiles on phone-sized viewports than on tablet-sized ones. Changing orientation or window size SHALL re-layout the pages and keep the first post of the current page visible.

#### Scenario: iPad portrait vs iPhone portrait
- **WHEN** the same posts are shown in flip view on an iPad in portrait and on an iPhone in portrait
- **THEN** each iPhone screen page shows fewer tiles than an iPad screen page

#### Scenario: Rotating the device
- **WHEN** the user rotates the iPad while looking at a screen page whose first post is P
- **THEN** after the re-layout the visible screen page contains post P

### Requirement: Horizontal paging with touch
The user SHALL move between screen pages by swiping horizontally, and each swipe SHALL settle on exactly one full screen page.

#### Scenario: Swipe forward
- **WHEN** the user swipes from right to left on a screen page
- **THEN** the next screen page snaps fully into view

#### Scenario: Fast fling
- **WHEN** the user flings quickly across several pages' worth of distance
- **THEN** the view stops on the next screen page rather than skipping pages

### Requirement: Keyboard and mouse paging
On devices with a keyboard or mouse, the user SHALL be able to page forward and back with arrow keys, PageUp/PageDown and Space/Shift+Space, with visible previous/next buttons, and with the mouse wheel. Keys SHALL NOT page while the user is typing in an input.

#### Scenario: Arrow key
- **WHEN** the user presses the right arrow key in flip view
- **THEN** the next screen page is shown

#### Scenario: Mouse wheel
- **WHEN** the user scrolls the mouse wheel down by one notch
- **THEN** the view moves forward by exactly one screen page

#### Scenario: Typing in search
- **WHEN** the search field in the drawer has focus and the user presses the right arrow key
- **THEN** the caret moves in the field and the screen page does not change

### Requirement: Page position indicator
The flip view SHALL show the current screen page and the number of screen pages for the loaded server page.

#### Scenario: Indicator updates
- **WHEN** the user moves from screen page 2 to screen page 3 of 9
- **THEN** the indicator shows 3 of 9

### Requirement: Opening a post
Activating a tile SHALL open the post's URL in a new browser tab, as in the other views. Activating the feed name SHALL open that feed's filtered posts page.

#### Scenario: Tap on tile
- **WHEN** the user taps a tile anywhere outside the feed name
- **THEN** the post URL opens in a new tab and the flip view stays on the same screen page

#### Scenario: Tap on feed name
- **WHEN** the user taps the feed name on a tile
- **THEN** the posts page for that feed is shown in flip view

#### Scenario: Swipe does not open
- **WHEN** the user starts a swipe on a tile
- **THEN** the page changes and no post is opened

### Requirement: Sidebar available as a drawer
In flip view the sidebar content of the posts page SHALL be reachable through a button that stays visible over the mosaic. The button SHALL open it as an overlay drawer that also contains the view selector. The drawer SHALL close on a close control, outside tap or Escape.

#### Scenario: Open drawer
- **WHEN** the user taps the menu button in flip view
- **THEN** a drawer shows the search field, tags, feeds and the view selector over the mosaic

#### Scenario: Close drawer
- **WHEN** the drawer is open and the user taps outside it or presses Escape
- **THEN** the drawer closes and the mosaic is on the same screen page as before

#### Scenario: Leave flip view
- **WHEN** the user opens the drawer and selects card in the view selector
- **THEN** the posts page is shown in card view with the normal sidebar layout

### Requirement: Current filter visible
The flip view SHALL show the current context over the mosaic: the tag, feed name or search term in effect, matching the posts heading of the other views.

#### Scenario: Tag filter label
- **WHEN** flip view shows the posts for tag News
- **THEN** a label "#News" is visible over the mosaic

### Requirement: Feed and tag filters
Filtering by feed or tag SHALL show exactly the posts the other views show for that filter, in flip view.

#### Scenario: Tag from drawer
- **WHEN** the user opens the drawer in flip view and taps tag "#News"
- **THEN** flip view shows the same posts the list view shows for tag News

#### Scenario: Feed filter
- **WHEN** the user opens a feed's posts page in flip view
- **THEN** only that feed's posts are shown as tiles

### Requirement: Search
Searching from the drawer SHALL show the same matching posts the other views show for that search term, and the search term SHALL stay filled in the search field.

#### Scenario: Search submit
- **WHEN** the user enters "kernel" in the drawer search field and submits
- **THEN** flip view shows the same posts the list view shows for the search "kernel"

### Requirement: Hidden feeds
Posts of feeds marked hidden SHALL be left out of the flip view on the home page and shown when that feed or one of its tags is opened as a filter, as in the other views.

#### Scenario: Home hides hidden feed
- **WHEN** feed H is hidden and the user opens the home page in flip view
- **THEN** no tile for a post of feed H is shown

#### Scenario: Feed filter shows hidden feed
- **WHEN** feed H is hidden and the user opens the posts page of feed H in flip view
- **THEN** feed H's posts are shown as tiles

#### Scenario: Hidden marker in drawer
- **WHEN** feed H is hidden and the user opens the drawer in flip view
- **THEN** feed H is listed with the same "Posts hidden by default" marker as in the other views

### Requirement: Server pagination
Flip view SHALL show the same posts for a server page as the other views. The last screen page SHALL offer "Previous page" when the server page is above 1, and "Next page" when the server page is full, using the same rules as the other views.

#### Scenario: Full server page
- **WHEN** server page 1 holds the maximum number of posts and the user reaches the last screen page
- **THEN** a "Next page" control is shown and activating it loads server page 2 in flip view starting at its first screen page

#### Scenario: Partial last server page
- **WHEN** the current server page holds fewer than the maximum number of posts
- **THEN** no "Next page" control is shown

#### Scenario: Previous page
- **WHEN** the user is on server page 2 and reaches the last screen page
- **THEN** a "Previous page" control is shown and activating it loads server page 1 in flip view

#### Scenario: Filters kept while paging
- **WHEN** the user is on a tag or feed filter in flip view and activates "Next page"
- **THEN** the next server page of the same tag or feed is shown

#### Scenario: Search paging matches other views
- **WHEN** the user is on a search result in flip view and activates "Next page"
- **THEN** the same page is loaded as when activating "Next Page" in list view for that search

### Requirement: Empty result
When there are no posts to show, the flip view SHALL show a message saying there are no posts instead of an empty screen, and the drawer button SHALL remain available.

#### Scenario: Search without matches
- **WHEN** the user searches for a term that matches no post in flip view
- **THEN** a "No posts" message is shown and the user can open the drawer to change the search

### Requirement: Feed reload status in flip view
Feeds that are being reloaded SHALL keep showing their reload progress and the "Reloaded, refresh to show changes" link in the drawer while the flip view is active.

#### Scenario: Reload finishes while drawer is closed
- **WHEN** a feed finishes reloading while the user is paging through flip view with the drawer closed
- **THEN** opening the drawer shows that feed's "Reloaded, refresh to show changes" link

### Requirement: Light and dark theme
The flip view SHALL follow the configured light, dark or system theme like the other views. Text over thumbnails SHALL stay legible in both themes.

#### Scenario: Dark theme
- **WHEN** the app theme is dark and flip view is shown
- **THEN** the page background, controls and drawer use the dark colours used by the other views

#### Scenario: Light theme over bright image
- **WHEN** the app theme is light and a tile's thumbnail is mostly white
- **THEN** the tile's title and feed name are still readable

### Requirement: Reduced motion
When the user has asked the system to reduce motion, page changes triggered by keys, buttons or the mouse wheel SHALL jump to the target page without an animated scroll.

#### Scenario: Reduced motion set
- **WHEN** reduced motion is enabled in the OS and the user presses the right arrow key
- **THEN** the next screen page is shown immediately without a smooth-scroll animation
