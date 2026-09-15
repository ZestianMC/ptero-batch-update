# ptero-batch-update

Blueprint extension for Pterodactyl: push a saved file to the same path on many servers.

Open any file in the panel's file editor, click **Save**, then **Batch save…**, pick the
target servers and confirm. Each target reports `Saved`, `Skipped: file not found`
(the file must already exist on the target) or an error with its reason.

The panel's client API rate limit (240 requests/min per user by default) caps a single
batch at roughly 240 servers; larger fleets should be done in chunks.

## Requirements

- Pterodactyl Panel 1.11 or newer
- [Blueprint](https://blueprint.zip) installed on the panel (extension framework)
- Node.js and Yarn on the panel host (Blueprint needs them to rebuild the panel frontend)

## Install

### 1. Install Blueprint on the panel (once)

Follow the official guide at <https://blueprint.zip/docs> (Getting started → Installation).
In short, on the panel host:

    cd /var/www/pterodactyl
    # download the latest release zip from https://github.com/BlueprintFramework/framework/releases
    unzip -o release.zip
    chmod +x blueprint.sh && bash blueprint.sh

Confirm with `blueprint -v`.

### 2. Install this extension

Option A — clone and deploy (recommended, easy to update):

    git clone https://github.com/ZestianMC/ptero-batch-update /opt/ptero-batch-update
    bash /opt/ptero-batch-update/scripts/deploy.sh          # panel root defaults to /var/www/pterodactyl

Option B — manual package:

    bash scripts/package.sh                                 # → batchupdate.blueprint
    mv batchupdate.blueprint /var/www/pterodactyl/
    cd /var/www/pterodactyl && blueprint -install batchupdate

Blueprint rebuilds the panel frontend during install; this takes a few minutes.

### 3. Update

    bash /opt/ptero-batch-update/scripts/deploy.sh

It pulls the latest commit, packages it and re-runs `blueprint -install batchupdate`.

### Remove

    cd /var/www/pterodactyl && blueprint -remove batchupdate

## Use

1. Open a server → **Files** → open the file you want to distribute.
2. Edit and click **Save** (the batch uses the last saved version).
3. Click **Batch save…** below the save button.
4. Search / select target servers (only servers you may write files on are listed) and click **Save to N servers**.
5. Read the per-server results. Writes are logged in the panel log as `batchupdate.write`
   with user id, server uuid, path and status.

## Development

PHP tests (Docker, no local PHP needed). Commands mount the repo root and run from `tests/php`:

    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD:/app" -w /app/tests/php composer:2 install
    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD:/app" -w /app/tests/php php:8.2-cli vendor/bin/phpunit

On Linux/macOS, drop the `MSYS_NO_PATHCONV=1` prefix (it's only needed for Git Bash on Windows).

PHP lint:

    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD/batchupdate:/ext" php:8.2-cli sh -c 'for f in $(find /ext -name "*.php"); do php -l "$f" || exit 1; done'

TypeScript:

    cd tests/ts && npm install && npm run typecheck && npm test

Packaging uses `git archive`, so only committed files end up in `batchupdate.blueprint`.

## Manual smoke test

1. Servers A and B have `/plugins/zCosmetics/cosmetics/balloons.yml`; server C does not.
2. On A, open the file, change it, click **Save**, then **Batch save…**.
3. Select B and C, click **Save to 2 servers**.
4. Expect: B `✓ Saved`, C `– Skipped: file not found`, amber banner "Saved to 1 of 2. 1 skipped, 0 failed."
5. Open the file on B: content matches A.
6. Log in as a subuser without `file.update` on B; repeat → B `✗ Error: no permission`.
