# ptero-batch-update

Blueprint extension for Pterodactyl: push a saved file, or upload files, to many servers at once.

- **Batch save** — open any file in the panel's file editor, click **Save**, then **Batch save…**,
  pick the target servers and confirm. The file must already exist on each target.
- **Batch upload** — in the file manager, click **Batch upload…** next to Upload, choose one or
  more files, pick targets and confirm. Files land in the directory you have open; targets
  without that directory are skipped, existing files with the same name are overwritten.
- **Batch new folder** — in the file manager, click **Batch new folder…**, type a name, pick
  targets and confirm. The folder is created inside the directory you have open; targets
  without that directory, or where the folder already exists, are skipped.

Each target reports `Saved`, `Skipped: <reason>` or `Error: <reason>`, and failed targets can be
retried from the results view.

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

Batch save (needs `file.update` on each target):

1. Open a server → **Files** → open the file you want to distribute.
2. Edit and click **Save** (the batch uses the last saved version).
3. Click **Batch save…** below the save button.
4. Search / select target servers and click **Save to N servers**.

Batch upload (needs `file.create` on each target):

1. Open a server → **Files** → navigate into the directory the files should go to.
2. Click **Batch upload…**, choose the file(s).
3. Select target servers and click **Upload to N servers**. Bytes go from your browser straight
   to each server's Wings, exactly like the panel's own Upload button, so large files are fine.

Batch new folder (needs `file.create` on each target):

1. Open a server → **Files** → navigate into the parent directory.
2. Click **Batch new folder…**, type the folder name, select targets, click **Create on N servers**.

Only servers you hold the needed permission on are listed; root admins see every server.
Batch saves are logged in the panel log as `batchupdate.write` with user id, server uuid, path
and status. Uploads are logged by Wings like any other upload.

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
