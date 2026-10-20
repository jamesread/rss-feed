# Spec Delta

## ADDED Requirements

### Requirement: Feed line can name the feed's website
A feed line MAY contain one `site=<url>` token after the name, among or after the tags. The value SHALL be an `http://` or `https://` URL. It sets the website used for the feed's favicon. A `site=` value that is not an http(s) URL SHALL be ignored. The token SHALL NOT be treated as a tag.

#### Scenario: Site token after the name
- **WHEN** a line reads `https://news.google.com/rss/search?q=Acme Acme-News site=https://www.example.com #acme`
- **THEN** the feed is named `Acme-News`, has the tag `#acme`, and has the site `https://www.example.com`

#### Scenario: No site token
- **WHEN** a line has no `site=` token
- **THEN** the feed has no configured site

#### Scenario: Invalid site value
- **WHEN** a line contains `site=example.com`
- **THEN** the feed has no configured site and the line is otherwise parsed normally

### Requirement: Site token survives config round trips
When the feed configuration is written back as text or encoded for a URL, each feed's `site=` token SHALL be kept, so that decoding gives the same site.

#### Scenario: URL-encoded config
- **WHEN** a config with a feed that has `site=https://www.example.org` is encoded for a URL and decoded again
- **THEN** the decoded feed has the site `https://www.example.org`
