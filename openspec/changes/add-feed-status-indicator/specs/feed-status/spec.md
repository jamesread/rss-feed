# Spec Delta

## Purpose

Records the outcome of each feed's most recent fetch and shows feeds whose last fetch failed in the feed list, so broken feeds are noticed without reading container logs.

## ADDED Requirements

### Requirement: Last fetch outcome is recorded
After each fetch attempt, the system SHALL record whether it succeeded. On success it SHALL record the time and clear any previous error. On failure it SHALL record a short reason and keep the time of the last success.

#### Scenario: HTTP error
- **WHEN** a feed URL returns HTTP 404
- **THEN** the feed's last fetch error is `HTTP 404` and its last success time is unchanged

#### Scenario: Unreachable host
- **WHEN** a feed's host cannot be reached
- **THEN** the feed's last fetch error describes the connection failure

#### Scenario: Recovery
- **WHEN** a feed with a recorded error is fetched successfully
- **THEN** its error is cleared and its last success time is the time of that fetch

### Requirement: Invalid feed content is a failed fetch
A response that is not valid RSS or Atom XML SHALL be recorded as a failed fetch with the reason `Invalid feed content`. A valid feed with no items SHALL count as a success.

#### Scenario: HTML instead of XML
- **WHEN** a feed URL returns an HTML page with status 200
- **THEN** the feed's last fetch error is `Invalid feed content`

#### Scenario: Empty but valid feed
- **WHEN** a feed returns a valid RSS document with no items
- **THEN** the fetch counts as a success and no error is recorded

### Requirement: Failing feeds are marked in the feed list
In the feed list, a feed whose last fetch failed SHALL show a small red dot directly right of its name, vertically centered on the name text. Feeds without an error SHALL show no dot. The same list is shown in the flip view drawer.

#### Scenario: Failing feed
- **WHEN** the feed list is shown and a feed's last fetch failed
- **THEN** a red dot appears right of that feed's name, centered on the name's line

#### Scenario: Healthy feed
- **WHEN** a feed's last fetch succeeded
- **THEN** no dot is shown next to its name

#### Scenario: Long feed name
- **WHEN** a failing feed's name wraps onto two lines
- **THEN** the dot stays next to the end of the name and does not overlap it

### Requirement: Failure details are available on the marker
The red dot SHALL expose the failure reason and the time of the last successful fetch (or "never") as a tooltip on hover and as its accessible label.

#### Scenario: Hover
- **WHEN** a pointer hovers over the dot of a feed that last failed with `HTTP 404` and last succeeded 2 days ago
- **THEN** a tooltip shows the reason `HTTP 404` and that the last success was 2 days ago

#### Scenario: Never succeeded
- **WHEN** a newly added feed has never been fetched successfully and its last fetch failed
- **THEN** the tooltip says the last success was never
