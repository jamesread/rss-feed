# Spec Delta

## Purpose

Defines which tags the published container image receives, so deployments can follow the newest build or pin a specific commit or release.

## ADDED Requirements

### Requirement: Branch builds are tagged with the commit and latest
Every push to `main` SHALL publish the image tagged `sha-<short commit sha>` and `latest`, after the tests pass. A push to `main` SHALL NOT add a version tag, whether or not a version tag exists in the repository.

#### Scenario: Push to main
- **WHEN** a commit is pushed to `main` and the tests pass
- **THEN** the image is published as `sha-<short sha>` and `latest`, with no version tag

#### Scenario: Tests fail
- **WHEN** a commit is pushed to `main` and the tests fail
- **THEN** no image is published

### Requirement: Version tag builds are tagged with the version
Pushing a git tag of the form `v<semver>` (for example `v3.0.0` or `v3.0.0-rc.1`) SHALL build the tagged commit, run the tests, and publish the image tagged with the version without the leading `v` (`3.0.0`, `3.0.0-rc.1`) and `sha-<short commit sha>`. A version tag build SHALL NOT move `latest`.

#### Scenario: Release tag
- **WHEN** the tag `v3.0.0` is pushed and the tests pass
- **THEN** the image is published as `3.0.0` and `sha-<short sha>`, and `latest` is unchanged

#### Scenario: Pre-release tag
- **WHEN** the tag `v3.0.0-rc.1` is pushed and the tests pass
- **THEN** the image is published as `3.0.0-rc.1`

### Requirement: Non-version tags publish nothing extra
A pushed git tag that does not start with `v` SHALL NOT start a publish. A `v` tag that is not a valid semantic version SHALL NOT produce a version image tag.

#### Scenario: Other tag
- **WHEN** a tag named `backup-2026` is pushed
- **THEN** no image build is started

#### Scenario: Invalid version
- **WHEN** a tag named `vnext` is pushed
- **THEN** no image tag named after it is published
