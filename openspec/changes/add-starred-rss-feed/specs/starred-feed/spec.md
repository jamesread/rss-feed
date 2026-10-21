# Spec Delta

## ADDED Requirements

### Requirement: Starred posts are published as an RSS feed
`GET /favorites` SHALL return a valid RSS 2.0 document with the content type `application/rss+xml`. It SHALL contain one item per starred post, most recently starred first. The feed is generated from the current star list on every request.

#### Scenario: Feed lists starred posts
- **WHEN** posts A (starred yesterday) and B (starred today) are starred and a client requests `/favorites`
- **THEN** the response is RSS 2.0 with two items, B first and then A

#### Scenario: Empty list
- **WHEN** no posts are starred and a client requests `/favorites`
- **THEN** the response is a valid RSS 2.0 feed with a channel and no items

### Requirement: The feed follows starring and unstarring
A post SHALL appear in the feed as soon as it is starred and SHALL be absent as soon as it is unstarred. The feed SHALL not be cached by the server.

#### Scenario: Star adds an item
- **WHEN** a reader stars post C and a client then requests `/favorites`
- **THEN** the feed contains an item for C

#### Scenario: Unstar removes an item
- **WHEN** a reader unstars post A and a client then requests `/favorites`
- **THEN** the feed contains no item for A

### Requirement: Feed items describe the starred post
Each item SHALL contain the post title, the post URL as `link`, the post URL as `guid` (`isPermaLink="true"`), the stored description as plain text, the star time as `pubDate` (RFC 822), and the name of the post's feed as `category`. The channel SHALL be titled `<APP_NAME> – Starred`, link to the starred view, and use absolute URLs that respect the reverse proxy host and path prefix. All text SHALL be XML-escaped.

#### Scenario: Item content
- **WHEN** the post `Rust 2.0 released` from feed `Tech News`, linking to `https://example.com/rust`, was starred at 2026-10-21 08:00 UTC
- **THEN** its item has that title, `link` and `guid` `https://example.com/rust`, `category` `Tech News` and `pubDate` `Wed, 21 Oct 2026 08:00:00 +0000`

#### Scenario: Special characters
- **WHEN** a starred post's title is `AT&T <beta> "test"`
- **THEN** the feed is well-formed XML and a feed reader shows the title `AT&T <beta> "test"`

#### Scenario: Behind a sub-path proxy
- **WHEN** the app runs behind a proxy at `https://example.org/rss/` with `X-Forwarded-Prefix: /rss`
- **THEN** the channel link is `https://example.org/rss/starred`

### Requirement: Feed items include the thumbnail
When a starred post has a kept thumbnail, its item SHALL include the image as an absolute URL in both an `<enclosure>` (with the image's MIME type and size in bytes) and a `<media:content medium="image">` element from the Media RSS namespace. Items without a thumbnail, or whose thumbnail file is missing, SHALL omit both elements and stay valid.

#### Scenario: Thumbnail in the item
- **WHEN** a starred post has the kept thumbnail `starred/ab12.jpeg` of 20480 bytes and the site runs at `https://news.example.com/`
- **THEN** its item has `<enclosure url="https://news.example.com/storage/starred/ab12.jpeg" type="image/jpeg" length="20480"/>` and a `media:content` element with the same URL

#### Scenario: No thumbnail
- **WHEN** a starred post has no thumbnail
- **THEN** its item has no `enclosure` and no `media:content`, and the feed is still valid RSS 2.0

### Requirement: The feed can be discovered from the starred view
The starred view SHALL show a link to `/favorites` labelled as the RSS feed of starred posts, and every page of the app SHALL announce the feed in its HTML head with `<link rel="alternate" type="application/rss+xml">` so browsers and feed readers can find it.

#### Scenario: Subscribe from the starred view
- **WHEN** a reader opens `/starred`
- **THEN** the page shows an RSS link pointing to `/favorites`, and its HTML head contains an alternate link of type `application/rss+xml` to the same URL
