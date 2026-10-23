# Tasks

## 1. Workflow and docs

- [x] 1.1 In `.github/workflows/action.yml`, add `tags: ['v*']` to the `push` trigger and `type=semver,pattern={{version}}` to the metadata tags (design decisions 1 and 2). Update the comment above the metadata step.

  Verify:
  - `actionlint .github/workflows/action.yml` passes, or `python3 -c 'import yaml,sys; yaml.safe_load(open(sys.argv[1]))'` if actionlint isn't available.
  - The diff touches only the trigger, the tag rule and the comment.
  - `tests.yml` is unchanged.
- [x] 1.2 Update the README's Docker Usage section (decision 4) to cover:
  - The image tags (`latest`, `sha-<commit>`, `<version>`).
  - Pinning a version in the `docker run` and compose examples.
  - Cutting a release with `git tag vX.Y.Z && git push origin vX.Y.Z`.
  - That fork releases start at `v3.0.0`, and not running `git push --tags`.

  Verify: `grep -n "git push origin v\|:3\.\|sha-" README.md` shows the new lines.

## 2. End-to-end check (after the user approves pushing)

- [x] 2.1 After the change is pushed to `main`, confirm the `main` run still publishes only `sha-<sha>` and `latest`.
  Verify: `gh run view` shows the metadata step output without a version tag, and `skopeo list-tags docker://quay.io/michard/rss-feed` shows no new version tag.
- [x] 2.2 With the user's go-ahead, push the pre-release tag `v3.0.0-rc.1`.
  Verify:
  - The run is triggered by the tag and the tests pass.
  - quay.io gets `3.0.0-rc.1` and `sha-<sha>`.
  - The digest of `latest` is unchanged (compare `skopeo inspect` before and after).
- [x] 2.3 With the user's go-ahead, push the first release tag `v3.0.0`.
  Verify: quay.io gets `3.0.0`, `sha-<sha>` and `latest`, all with the same digest, and `docker pull quay.io/michard/rss-feed:3.0.0` works.
