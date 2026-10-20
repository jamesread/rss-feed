# Spec Delta

## Purpose

Detects posts in a listing that are the same story from different feeds and shows each story once, attributed to every feed it came from.

## ADDED Requirements

### Requirement: Duplicate posts are detected
Two posts in the same listing page SHALL be treated as the same story when their URLs are equal, or when their normalized titles are equal. A normalized title is lower-cased, with every run of non-letter, non-digit characters replaced by a single space, and trimmed. Normalized titles shorter than 20 characters SHALL NOT be used for matching.

#### Scenario: Same link in two feeds
- **WHEN** Acme-News and Globex-News both contain a post linking to the same URL
- **THEN** they are treated as one story

#### Scenario: Same headline, different link
- **WHEN** two feeds have posts titled `Acme and Globex sign AI partnership` and `Acme & Globex sign AI partnership!` with different URLs
- **THEN** they are treated as one story

#### Scenario: Short generic title
- **WHEN** two different posts are both titled `Weekly update`
- **THEN** they are not treated as one story

### Requirement: A duplicated story is shown once with all its feeds
For each group of duplicates, the listing SHALL show only the newest post, at that post's position. It SHALL name every distinct feed of the group, newest post's feed first, each linking to its feed view, in card, list, compact, grid and flip layouts.

#### Scenario: Story in three feeds
- **WHEN** the same story is in Acme-News (newest), Globex-News and Initech-News
- **THEN** it appears once, at the Acme-News post's position, labelled `Acme-News · Globex-News · Initech-News`

#### Scenario: Flip tile with several feeds
- **WHEN** a story from three feeds is shown on a small flip tile
- **THEN** the feed names truncate with an ellipsis and do not overlap the title or time

#### Scenario: No duplicates
- **WHEN** a post has no duplicate in the listing page
- **THEN** it is shown exactly as today, with its single feed name

### Requirement: Collapsing works within one page
Duplicates SHALL be collapsed among the posts loaded for one listing page. A page MAY therefore show fewer than the page size. The next-page control SHALL be shown whenever more posts exist beyond the current page, regardless of how many remain after collapsing.

#### Scenario: Page with collapsed posts
- **WHEN** 100 posts are loaded for page 1, 6 are duplicates, and more posts exist
- **THEN** page 1 shows 94 entries and a next-page control, in both the standard layouts and flip view

#### Scenario: Last page
- **WHEN** fewer than 100 posts exist for the last page
- **THEN** no next-page control is shown
