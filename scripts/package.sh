#!/usr/bin/env bash
# Builds batchupdate.blueprint (a zip of the committed batchupdate/ tree) in the repo root.
set -euo pipefail
cd "$(dirname "$0")/.."
git archive --format=zip --output=batchupdate.blueprint HEAD:batchupdate
echo "wrote $(pwd)/batchupdate.blueprint (from committed files only — commit first)"
