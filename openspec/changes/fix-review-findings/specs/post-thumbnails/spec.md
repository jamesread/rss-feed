# Spec Delta

## Purpose

Defines how thumbnail images for posts are discovered from the post page, fetched and stored, and which safety limits apply to those outbound requests.

## ADDED Requirements

### Requirement: Only raster image types are stored
A fetched thumbnail SHALL be stored only if its detected content type is JPEG, PNG, GIF, WebP or AVIF. Any other type, including SVG, SHALL be discarded, and the post keeps no thumbnail.

#### Scenario: SVG thumbnail rejected
- **WHEN** a post page's `og:image` points to an SVG image
- **THEN** no thumbnail file is stored and the post has no thumbnail

#### Scenario: JPEG thumbnail stored
- **WHEN** a post page's `og:image` points to a JPEG image under 1 MB
- **THEN** the image is stored and set as the post's thumbnail

### Requirement: Private network addresses are never fetched
Both the post page request and the image request SHALL be skipped if the target host resolves to a private, loopback, link-local or reserved address.

#### Scenario: Post URL on a private address
- **WHEN** a post's URL resolves to `192.168.1.10`
- **THEN** the post page is not requested and no thumbnail is stored

#### Scenario: Image URL on loopback
- **WHEN** a post page's `og:image` points to `http://127.0.0.1/image.png`
- **THEN** the image is not requested and no thumbnail is stored

### Requirement: Redirect targets are checked
The post page and image requests SHALL follow redirects only to targets that pass the private network address check. A redirect to a disallowed address SHALL be treated as a failed fetch.

#### Scenario: Redirect to a public host
- **WHEN** a post URL redirects (for example a news aggregator link) to a public article page
- **THEN** the article page is fetched and its `og:image` is used

#### Scenario: Redirect to a private address
- **WHEN** the image URL responds with a 302 redirect to `http://10.0.0.5/image.png`
- **THEN** the redirect target is not requested and no thumbnail is stored
