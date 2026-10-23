# Proposal

## Why

The image on quay.io is only tagged `latest` and `sha-<commit>`. Neither says which release a deployment runs, and pinning a deployment to a known-good release means copying a commit hash. Version tags such as `3.0.0` make releases visible and easy to pin or roll back to. The companion GHCR workflow in smichard/rss-feed#1 already does this.

## What Changes

- **Tag pushes trigger a build:** the quay.io publish workflow (`.github/workflows/action.yml`) also runs when a git tag matching `v*` is pushed.
- **Version image tag:** when the build comes from a version tag such as `v3.0.0`, the image is also tagged with the version without the `v` (`3.0.0`). This uses the same `type=semver,pattern={{version}}` rule as the PR.
- **Only when a version tag is present:** pushes to `main` keep producing exactly `sha-<commit>` and `latest`. No version tag is added, and a build fails no more often than before because no tag exists.
- **Tag builds are tested first:** tests run before publishing, as on `main`.
- **README:** explains how to cut a release (`git tag v3.0.0 && git push origin v3.0.0`) and how to pin a version in `docker run` and compose.

Out of scope:
- GHCR publishing, which is that PR's job.
- Build caching.
- `major` and `major.minor` alias tags.
- Automatic version bumps or release notes.

## Capabilities

### New Capabilities
- `container-publishing`: which tags the published container image receives for branch builds and version tag builds.

### Modified Capabilities
- None.

## Impact

- **Files:**
  - `.github/workflows/action.yml`: new trigger and one extra metadata tag rule.
  - `README.md`: release and pinning notes.
- **No changes** to application code, the Dockerfile or the tests workflow.
- **Registry:** quay.io starts receiving version tags once the first `v*` tag is pushed to `smichard/rss-reader`.
- **Releases and the upstream tags:**
  - The local clone carries 33 tags from upstream danb/rss (up to `v2.2.3`), and none are on the GitHub remote yet.
  - Fork releases start at `v3.0.0`, clearly above the inherited `v2.2.3`, and those old tags should not be pushed in bulk. GitHub skips workflow runs when more than three tags are pushed at once, so a bulk push wouldn't build them anyway, but it would still be confusing.
