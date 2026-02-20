#!/bin/bash

# Usage: ./git-tag.sh 1.3.0
# or:    ./git-tag.sh v1.3.0

set -e

VERSION="$1"

if [ -z "$VERSION" ]; then
  echo "Usage: ./git-tag.sh 1.3.0"
  exit 1
fi

# ajoute v si absent
if [[ $VERSION != v* ]]; then
  VERSION="v$VERSION"
fi

echo "🏷  Preparing tag $VERSION"

echo "🧹 Deleting local tag (if exists)"
git tag -d "$VERSION" 2>/dev/null || true

echo "🧹 Deleting remote tag (if exists)"
git push origin ":refs/tags/$VERSION" 2>/dev/null || true

echo "🏷 Creating tag"
git tag "$VERSION"

echo "🚀 Pushing tag"
git push origin "$VERSION"

echo "✅ Done → GitHub Action should trigger"

