# Spec Delta

## ADDED Requirements

### Requirement: Feeds menu lists the configured feeds as rows
The Feeds menu of the settings page SHALL show every feed from the feeds file as one editable row, in file order, including hidden feeds. Each row SHALL have four fields:
- Title: the feed name with spaces.
- Tags: written without `#`, separated by `, `.
- Feed URL.
- Picture origin: the `site=` value, empty when none is set.

Title, Tags and Feed URL SHALL be marked as required.

#### Scenario: Existing line shown as a row
- **WHEN** the feeds file contains `https://news.google.com/rss/search?q=Acme Acme_News site=https://www.example.com #acme #news`
- **THEN** a row shows the title `Acme News`, the tags `acme, news`, that URL and the picture origin `https://www.example.com`

#### Scenario: Empty or missing file
- **WHEN** the feeds file is empty or does not exist
- **THEN** the Feeds menu shows no rows and offers to add one

### Requirement: Feed rows can be added and removed
The Feeds menu SHALL let a reader add a new empty row at the end of the list and remove any row. Changes SHALL only take effect when the reader saves.

#### Scenario: Add a feed
- **WHEN** a reader adds a row, fills in title `Tech News`, tags `tech`, URL `https://example.com/feed.xml`, and saves
- **THEN** the feed is listed in the posts views' sidebar on the next page load, with the tag `#tech`

#### Scenario: Remove a feed
- **WHEN** a reader removes a row and saves
- **THEN** that feed no longer appears in the posts views

#### Scenario: Leave without saving
- **WHEN** a reader adds a row and leaves the page without saving
- **THEN** the feeds file is unchanged

### Requirement: Tags are entered comma-separated
The Tags field SHALL accept one or more tags separated by commas, with or without spaces after the commas. Surrounding whitespace and a leading `#` on a tag SHALL be ignored, and empty entries dropped. An info icon next to the Tags label SHALL show, on hover and on keyboard focus, that tags are separated by commas.

#### Scenario: With and without spaces
- **WHEN** the Tags field is `tag_1,tag_2` or `tag_1, tag_2` or ` #tag_1 , tag_2, `
- **THEN** the feed is saved with exactly the tags `#tag_1` and `#tag_2`, in that order

#### Scenario: Hint on hover
- **WHEN** a reader hovers over or focuses the info icon next to the Tags label
- **THEN** a hint says that several tags are separated by commas, for example `tag_1, tag_2`

### Requirement: Feed rows are validated before saving
Saving SHALL write nothing and show an error at each invalid field when any row fails one of these checks:
- Title, Tags or Feed URL is empty.
- Feed URL or picture origin is not an `http` or `https` URL, or contains whitespace.
- A tag contains whitespace or a `#` after its start.
- A title contains `[` or `]`.
- Two rows have the same Feed URL.

The picture origin MAY be empty.

#### Scenario: Missing tags
- **WHEN** a row has a title and URL but an empty Tags field and the reader saves
- **THEN** nothing is saved and the Tags field of that row shows that it is required

#### Scenario: Invalid URL
- **WHEN** a row's Feed URL is `example.com/feed`
- **THEN** nothing is saved and the URL field shows that it must start with `http://` or `https://`

#### Scenario: Duplicate URL
- **WHEN** two rows have the same Feed URL
- **THEN** nothing is saved and the second row's URL field shows that the feed is already listed

#### Scenario: Optional picture origin
- **WHEN** a row has title, tags and URL and an empty picture origin
- **THEN** it is saved without a `site=` token

### Requirement: Saving writes the feeds file
Saving the Feeds menu SHALL replace the content of the feeds file (`APP_CONFIG_FILE`) with one line per row, in row order. Each line SHALL use the file format, which the application SHALL parse back to the same names, tags, URLs and picture origins. Spaces in titles SHALL be written as underscores. Comment and blank lines in the file are not kept. If the file cannot be written, the page SHALL show an error and the file SHALL be unchanged.

#### Scenario: Round trip
- **WHEN** a reader saves rows and then reloads the Feeds menu
- **THEN** the rows show the same titles, tags, URLs and picture origins as saved

#### Scenario: Comments dropped
- **WHEN** the feeds file contains comment lines and a reader saves the Feeds menu
- **THEN** the file contains only the feed lines

#### Scenario: File not writable
- **WHEN** the feeds file is read-only for the application and a reader saves
- **THEN** an error message says the feeds file could not be written and its content is unchanged

### Requirement: Saving keeps colours and hidden flags
A feed's colour and hidden flag from the feeds file SHALL NOT be shown in the Feeds menu. They SHALL be kept unchanged when the rows are saved, also when other fields of the row were edited. New rows SHALL have no colour and SHALL NOT be hidden.

#### Scenario: Colour and hidden kept
- **WHEN** the file contains `- https://example.com/feed-d.xml Cat_Facts[#0078b9] #cats` and a reader changes its tags to `cats, facts` and saves
- **THEN** the file contains `-https://example.com/feed-d.xml Cat_Facts[#0078b9] #cats #facts` and the feed stays hidden with the same colour
