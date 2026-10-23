# Design

## Context

- **`action.yml`:**
  - Runs on pushes to `main` and on `workflow_dispatch`.
  - Calls `tests.yml` (`workflow_call`), then builds `docker/Dockerfile` for amd64, arm64 and arm/v7.
  - Pushes to `quay.io/michard/rss-feed`.
  - `docker/metadata-action@v5` creates tags with `type=sha` and `type=raw,value=latest,enable={{is_default_branch}}`.
- **`tests.yml`:** triggers on `push` with `branches-ignore: [main]`. A trigger that has branch filters and no tag filters does not fire for tag pushes, so tag builds get their tests only through `action.yml`'s `workflow_call`. That is the behaviour we want.
- **smichard/rss-feed#1 (GHCR):** adds `tags: ['v*']` to the push trigger and `type=semver,pattern={{version}}` to the metadata.
- **Tags:** the remote `smichard/rss-reader` has no tags. The local clone has 33 upstream tags, `v0.1.0` to `v2.2.3`, and all are in `main`'s history.

## Goals / Non-Goals

**Goals:**
- Version image tags appear only when the build comes from a version tag. Branch builds stay exactly as they are.
- Same mechanism as the GHCR PR, so the two registries tag identically once both workflows exist.

**Non-Goals:**
- `{{major}}` and `{{major}}.{{minor}}` alias tags.
- Build cache.
- GitHub Releases.
- Version bumping.
- Retagging old upstream versions.

## Decisions

### 1. Trigger on `v*` tag pushes

```yaml
on:
  push:
    branches: [main]
    tags: ['v*']
  workflow_dispatch:
```

- With both `branches` and `tags` set, a branch push only fires for `main` and a tag push only fires for `v*` tags. Other tags (`backup-2026`) don't start a run.

### 2. Version tag via `type=semver,pattern={{version}}`

```yaml
tags: |
  type=sha
  type=raw,value=latest,enable={{is_default_branch}}
  type=semver,pattern={{version}}
```

- **How `type=semver` behaves:** it only produces a tag when the ref is a git tag that parses as semver. It strips the `v` (`v3.0.0` becomes `3.0.0`) and keeps pre-release suffixes (`3.0.0-rc.1`). On branch builds, and on tags like `vnext`, it emits nothing. That is the "only if available" behaviour, with no scripting.
- **Existing rules on a tag build:**
  - `type=sha` still emits `sha-<short sha>`.
  - `latest` stays off, because `{{is_default_branch}}` is false for tag refs. So pushing `v3.0.0` never moves `latest`, and `latest` keeps following `main`.

*Alternatives:*
- **Read `git describe` in a shell step and add the result:** this tags `main` builds with the nearest older tag. Two different images could then carry the same version, so it's rejected.
- **`type=ref,event=tag`:** keeps the `v` and also tags non-semver tags. Rejected to stay identical to the GHCR PR.

### 3. Concurrency stays as is

- `concurrency.group: ${{ github.workflow }}` with `cancel-in-progress: false` queues a tag build behind a running `main` build. Pushing a commit and its tag together therefore builds twice, one after the other, and neither is cancelled. The cost is a few extra minutes per release, which is acceptable.

### 4. README release notes

- **Docker Usage section:** add the available tags (`latest`, `sha-<commit>`, `<version>` once releases exist), how to pin (`quay.io/michard/rss-feed:3.0.0`), and how to cut a release:
  ```shell
  git tag v3.0.0
  git push origin v3.0.0
  ```
- **Advice:** fork versions start at `v3.0.0`, above the inherited `v2.2.3`. Push single tags, never `git push --tags`.

## Risks / Trade-offs

- **Pushing all local tags would publish old upstream versions under the fork's name.**
  - GitHub creates no push events when more than three tags are pushed at once, so a bulk push would not build them. Pushing a few individually would.
  - Mitigation: the README advice above. Optionally delete the upstream tags locally (not part of this change).
- **First version tag is unverified until pushed:** the behaviour of `docker/metadata-action` is well documented, but the first real tag push is the end-to-end check. Task 2 covers it with a pre-release tag, so a mistake doesn't produce a misleading release tag.
