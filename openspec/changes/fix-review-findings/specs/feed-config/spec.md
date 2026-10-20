# Spec Delta

## Purpose

Defines how the plaintext feed configuration file (`feeds.txt`) is turned into the list of feeds the application shows, including names, colors, tags and the hidden flag.

## ADDED Requirements

### Requirement: Fields are separated by any whitespace
Each feed line SHALL be split into URL, name and tags on any run of one or more spaces or tabs. Leading and trailing whitespace on a line SHALL be ignored.

#### Scenario: Double space between tags
- **WHEN** a line reads `https://example.net/en/feed Globex #Ecosystem  #globex`
- **THEN** the feed is named `Globex` and has the tags `#Ecosystem` and `#globex`

#### Scenario: Double space between URL and name
- **WHEN** a line reads `https://example.com/feed.xml  Example #news`
- **THEN** the feed is named `Example` and has the tag `#news`

#### Scenario: Tab separators
- **WHEN** a line separates URL, name and tags with tab characters
- **THEN** the feed is parsed the same as if single spaces had been used

### Requirement: Tags are an ordered list
A feed's tags SHALL always be exposed as an ordered list (a JSON array in page data), even if words that are not tags appear between tags.

#### Scenario: Non-tag word between tags
- **WHEN** a line reads `https://example.com/feed.xml Example #a stray #b`
- **THEN** the feed's tags are serialized as the JSON array `["#a", "#b"]`

#### Scenario: Page renders with irregular spacing
- **WHEN** `feeds.txt` contains lines with extra spaces or tabs
- **THEN** the posts page renders its posts, tags and feeds instead of a blank page
