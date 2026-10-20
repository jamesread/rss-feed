# Spec Delta

## Purpose

Lets a reader keep posts for later by starring them. Stars are stored in the reader's browser and listed in a dedicated view.

## ADDED Requirements

### Requirement: Posts can be starred and unstarred
Every post SHALL have a star control in card, list, compact, grid and flip layouts. Activating it SHALL star an unstarred post and unstar a starred one. Starred posts SHALL show a filled star; unstarred posts an outlined star. The control SHALL have an accessible label (`Star post` / `Unstar post`). In flip view, activating the star SHALL NOT open the post.

#### Scenario: Star in card view
- **WHEN** a reader clicks the outlined star on a post in card view
- **THEN** the star becomes filled and the post is starred

#### Scenario: Star on a flip tile
- **WHEN** a reader taps the star on a flip tile
- **THEN** the post is starred and the post link is not opened

#### Scenario: Same post elsewhere
- **WHEN** a post is starred and later shown again (another layout, page reload, or another tag view)
- **THEN** its star is shown filled

### Requirement: Starred posts are kept in the browser
Stars SHALL be stored in the browser's local storage, keyed by post URL, together with a copy of the post's title, URL, description, publish time, star time and feed name, color and URL. Starred posts SHALL remain listed after the post is pruned on the server or its feed is removed. If storage is unavailable, the star control SHALL still render without errors and starring SHALL have no lasting effect.

#### Scenario: Pruned post
- **WHEN** a starred post is deleted from the server by pruning
- **THEN** it still appears in the starred list with its title, link and feed name

#### Scenario: Storage blocked
- **WHEN** the browser blocks local storage (e.g. a private window)
- **THEN** the page and its star controls work without script errors

### Requirement: Starred view lists starred posts
The sidebar (and the flip view drawer) SHALL contain a `Starred` link with the number of starred posts. It SHALL open a view at `/starred` listing all starred posts in the current layout, most recently starred first. Posts unstarred in this view SHALL disappear from it. With no starred posts the view SHALL say so.

#### Scenario: Open the starred view
- **WHEN** a reader with 3 starred posts clicks `Starred (3)` in the sidebar
- **THEN** `/starred` shows those 3 posts, the most recently starred first

#### Scenario: Unstar in the starred view
- **WHEN** a reader unstars a post in the starred view
- **THEN** it is removed from the list and the count drops by one

#### Scenario: Empty
- **WHEN** a reader opens `/starred` with no starred posts
- **THEN** the view says there are no starred posts

#### Scenario: Flip view
- **WHEN** the starred view is opened while the flip layout is selected
- **THEN** the starred posts are shown as flip tiles, without next/previous server page tiles
