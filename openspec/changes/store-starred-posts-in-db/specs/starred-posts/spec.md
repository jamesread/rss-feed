# Spec Delta

## ADDED Requirements

### Requirement: Starred posts are kept on the server
Stars SHALL be stored in the application database, keyed by post URL, with a copy of the post details needed to show it (as kept in the browser before). There SHALL be one list for the whole installation, shared by every browser and device. Starred posts SHALL remain listed after the post is pruned or its feed is removed. Only `http` and `https` URLs SHALL be stored.

#### Scenario: Star on one device, see it on another
- **WHEN** a reader stars a post on a phone and then opens the site on a laptop
- **THEN** the laptop shows the post's star filled and lists it under `Starred`

#### Scenario: Pruned post
- **WHEN** a starred post is deleted from the server by pruning
- **THEN** it still appears in the starred list with its title, link and feed name

#### Scenario: Container recreated
- **WHEN** the container is recreated with the same `/app/storage` volume
- **THEN** all stars are still present

#### Scenario: Unsafe URL rejected
- **WHEN** a star request arrives for the URL `javascript:alert(1)`
- **THEN** the server rejects it and nothing is stored

### Requirement: Starred thumbnails are kept
When a post with a thumbnail is starred, the server SHALL keep its own copy of the thumbnail image for as long as the post is starred, and the starred post SHALL show that copy. Pruning posts SHALL NOT delete it. Unstarring SHALL delete the copy. A post without a thumbnail, or whose thumbnail file is already gone, SHALL be starred without one.

#### Scenario: Thumbnail survives pruning
- **WHEN** a post with a thumbnail is starred and the post is later pruned together with its original thumbnail
- **THEN** the starred post still shows its thumbnail

#### Scenario: Unstar deletes the copy
- **WHEN** a starred post with a kept thumbnail is unstarred
- **THEN** the kept thumbnail file is deleted

### Requirement: Starring and unstarring update the server
Activating the star control SHALL update the button immediately and send the change to the server. Starring a post that is already starred, or unstarring one that is not, SHALL leave the list unchanged and not fail. If the server rejects the request or cannot be reached, the button SHALL return to its previous state without script errors. Star and unstar requests SHALL be protected against cross-site request forgery: a request without the site's CSRF token SHALL be rejected.

#### Scenario: Server unreachable
- **WHEN** a reader taps the star while the server cannot be reached
- **THEN** the star returns to its previous state and the page keeps working

#### Scenario: Cross-site request
- **WHEN** another website submits a star request to the reader without the CSRF token
- **THEN** the request is rejected and nothing is stored

#### Scenario: Star twice
- **WHEN** a star request arrives for a URL that is already starred
- **THEN** the list still contains that post once, with its original star time

### Requirement: Browser stars are imported once
When a browser that still holds stars in local storage from an earlier version opens the app, those stars SHALL be sent to the server once and added to the shared list, keeping their original star times. Posts already on the server SHALL not be duplicated or changed. After a successful import the browser's local copy SHALL be removed; if the import fails, the local copy SHALL be kept and the import retried on a later page load.

#### Scenario: First visit after the update
- **WHEN** a browser with 4 locally starred posts opens the app after the update
- **THEN** those 4 posts appear in the shared starred list and the browser's local stars are removed

#### Scenario: Two devices with overlapping stars
- **WHEN** a phone imports posts A and B and a laptop later imports posts B and C
- **THEN** the shared list contains A, B and C once each

#### Scenario: Import fails
- **WHEN** the server cannot be reached during the import
- **THEN** the local stars are kept and the import is tried again on the next page load

## MODIFIED Requirements

### Requirement: Starred view lists starred posts
The sidebar (and the flip view drawer) SHALL contain a `Starred` link with the number of starred posts on the server. It SHALL open a view at `/starred` listing all starred posts in the current layout, most recently starred first. Posts unstarred in this view SHALL disappear from it. With no starred posts the view SHALL say so. Changes made on another device SHALL appear after the page is reloaded.

#### Scenario: Open the starred view
- **WHEN** the server holds 3 starred posts and a reader clicks `Starred (3)` in the sidebar
- **THEN** `/starred` shows those 3 posts, the most recently starred first

#### Scenario: Unstar in the starred view
- **WHEN** a reader unstars a post in the starred view
- **THEN** it is removed from the list and the count drops by one

#### Scenario: Empty
- **WHEN** a reader opens `/starred` and the server holds no starred posts
- **THEN** the view says there are no starred posts

#### Scenario: Flip view
- **WHEN** the starred view is opened while the flip layout is selected
- **THEN** the starred posts are shown as flip tiles, without next/previous server page tiles

## REMOVED Requirements

### Requirement: Starred posts are kept in the browser
**Reason**: Stars move to the server so they are shared across devices and survive cleared browser data.
**Migration**: Existing browser stars are imported once (see "Browser stars are imported once").
