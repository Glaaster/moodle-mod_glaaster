#!/bin/bash
# scripts/dist-split.sh
#
# Publishes mod/glaaster as a standalone plugin history (version.php at the repository root),
# so Moodle sites can consume it as a Git submodule.
#
#   <branch>  ->  dist/<branch>        e.g. main -> dist/main, moodle/4.5 -> dist/moodle/4.5
#   v<x.y.z>  ->  dist-v<x.y.z>        for every release tag reachable from <branch>
#
# Usage:
#   ./scripts/dist-split.sh <branch> [remote]
# Example:
#   ./scripts/dist-split.sh main origin

set -euo pipefail

BRANCH=${1:?Usage: dist-split.sh <branch> [remote]}
REMOTE=${2:-origin}
PREFIX="mod/glaaster"
DIST_BRANCH="dist/${BRANCH}"

cd "$(dirname "$0")/.."

# Split is deterministic: same source history gives the same commits, so dist branches only
# ever fast-forward and a plain (non-forced) push is enough.
SPLIT_SHA=$(git subtree split --prefix="$PREFIX" "$BRANCH")
git push "$REMOTE" "${SPLIT_SHA}:refs/heads/${DIST_BRANCH}"
echo "✅ ${DIST_BRANCH} -> ${SPLIT_SHA}"

# Mirror release tags not published yet (also backfills tags created before this script).
git fetch --tags --force "$REMOTE"
for TAG in $(git tag --merged "$BRANCH" --list 'v*'); do
  DIST_TAG="dist-${TAG}"
  if git rev-parse -q --verify "refs/tags/${DIST_TAG}" >/dev/null; then
    continue
  fi
  if ! git cat-file -e "${TAG}:${PREFIX}/version.php" 2>/dev/null; then
    echo "⚠️  ${TAG}: no ${PREFIX}/version.php, skipped"
    continue
  fi
  TAG_SPLIT_SHA=$(git subtree split --prefix="$PREFIX" "$TAG")
  git tag "$DIST_TAG" "$TAG_SPLIT_SHA"
  git push "$REMOTE" "refs/tags/${DIST_TAG}"
  echo "✅ ${DIST_TAG} -> ${TAG_SPLIT_SHA}"
done
