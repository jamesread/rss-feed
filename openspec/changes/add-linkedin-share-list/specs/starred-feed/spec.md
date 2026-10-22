# Spec Delta

## MODIFIED Requirements

### Requirement: The feed can be discovered from the starred view
Every page of the app SHALL announce the feed of starred posts in its HTML head with `<link rel="alternate" type="application/rss+xml">` pointing to `/favorites`, so browsers and feed readers can find it. The starred view SHALL NOT show a separate RSS feed link in its sidebar.

#### Scenario: Subscribe from the starred view
- **WHEN** a reader opens `/starred`
- **THEN** its HTML head contains an alternate link of type `application/rss+xml` to `/favorites`, and the sidebar shows no RSS feed link
