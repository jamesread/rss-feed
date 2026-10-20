# Spec Delta

## Purpose

Defines how configured feeds are fetched and refreshed in the background, and what happens and gets reported when a feed cannot be fetched.

## ADDED Requirements

### Requirement: Unreachable feeds are treated as a failed fetch
When fetching a feed fails because of a connection error, DNS failure or timeout, the refresh SHALL complete without raising an error. It SHALL store no new posts and SHALL record the attempt as the feed's last fetch time, the same as for a non-2xx response.

#### Scenario: Connection timeout
- **WHEN** a feed refresh runs and the feed host times out
- **THEN** the refresh completes, no posts are added and the feed's last fetch time is updated

#### Scenario: No failed jobs for dead feeds
- **WHEN** a feed's host is unreachable across several scheduled update runs
- **THEN** no failed queue jobs are recorded for that feed

#### Scenario: Backs off until the next interval
- **WHEN** a refresh of an unreachable feed has just completed
- **THEN** the feed is not considered outdated again until the configured update frequency has passed

### Requirement: Failed fetches are logged
Each failed feed fetch SHALL write a warning to the application log that contains the feed URL and the reason: the HTTP status code for non-2xx responses, or the error message for connection errors.

#### Scenario: Non-2xx response
- **WHEN** a feed returns HTTP 404
- **THEN** a warning containing the feed URL and status `404` is logged

#### Scenario: Connection error
- **WHEN** a feed's host cannot be resolved
- **THEN** a warning containing the feed URL and the connection error message is logged

#### Scenario: Successful fetch
- **WHEN** a feed returns HTTP 200
- **THEN** no warning is logged for that feed

### Requirement: Container logs include application warnings
The Docker image SHALL write application log entries to the container's standard error as well as the log file, so warnings from background feed refreshes appear in the container logs.

#### Scenario: Unreachable feed in docker logs
- **WHEN** the container is running and a configured feed is unreachable during a refresh
- **THEN** `docker logs` for the container shows the warning with that feed's URL
