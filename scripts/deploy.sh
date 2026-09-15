#!/usr/bin/env bash
# Run on the panel host from a clone of this repo:
#   bash scripts/deploy.sh [/var/www/pterodactyl]
# Pulls the latest commit, packages the extension, and (re)installs it with Blueprint.
set -euo pipefail

PANEL="${1:-/var/www/pterodactyl}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"

cd "$REPO"
git pull --ff-only
bash scripts/package.sh
mv -f batchupdate.blueprint "$PANEL/"
cd "$PANEL"
blueprint -install batchupdate
