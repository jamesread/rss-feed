# Spec Delta

## ADDED Requirements

### Requirement: Starred posts show a share button
Every starred post SHALL show a share button immediately to the left of its star button, in the list, card, compact, grid and flip views. Posts that are not starred SHALL NOT show it. Starring a post SHALL show the button and unstarring SHALL hide it, both without a page reload.

#### Scenario: Starred post
- **WHEN** a starred post is shown in the list view
- **THEN** a share button appears directly left of its star

#### Scenario: Not starred
- **WHEN** a post that is not starred is shown in any view
- **THEN** no share button is shown for it

#### Scenario: Star a post
- **WHEN** a reader stars a post
- **THEN** the share button appears left of the star without a page reload

### Requirement: Sidebar links to the share list
The sidebar of every posts view (home, tag, feed and starred, and the flip view drawer) SHALL show a `Share List - N` link, where N is the number of listed posts, on its own line directly below the `Starred - N` link, in the same font size as `Starred` and `Settings`. The link SHALL open the share view.

#### Scenario: In the starred view
- **WHEN** a reader opens the starred view
- **THEN** a `Share List - N` link appears below `Starred - N`, and clicking it opens `/share`

#### Scenario: Home view
- **WHEN** a reader opens the home view
- **THEN** a `Share List - N` link appears below `Starred - N`, and clicking it opens `/share`

### Requirement: Starred link shows the count after a dash
The sidebar's starred link SHALL read `Starred - N`, where N is the number of starred posts.

#### Scenario: Seven starred posts
- **WHEN** seven posts are starred and a reader opens any posts view
- **THEN** the sidebar shows `Starred - 7`
