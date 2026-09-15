# ptero-batch-update

Blueprint extension for Pterodactyl: push a saved file to the same path on many servers.

- Extension source: `batchupdate/` (see its README for install/use)
- Design: `docs/superpowers/specs/2026-09-14-batch-file-update-design.md`

## Tests

PHP (Docker, no local PHP needed). Commands mount the repo root and run from `tests/php`:

    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD:/app" -w /app/tests/php composer:2 install
    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD:/app" -w /app/tests/php php:8.2-cli vendor/bin/phpunit

On Linux/macOS, drop the `MSYS_NO_PATHCONV=1` prefix (it's only needed for Git Bash on Windows).

PHP lint:

    MSYS_NO_PATHCONV=1 docker run --rm -v "$PWD/batchupdate:/ext" php:8.2-cli sh -c 'for f in $(find /ext -name "*.php"); do php -l "$f" || exit 1; done'

TypeScript:

    cd tests/ts && npm install && npm run typecheck && npm test

## Package

    bash scripts/package.sh      # → batchupdate.blueprint

Packages committed files only (via `git archive`) — commit your changes first.

    # on the panel host:
    mv batchupdate.blueprint /var/www/pterodactyl/ && cd /var/www/pterodactyl && blueprint -install batchupdate

## Manual smoke test

1. Servers A and B have `/plugins/zCosmetics/cosmetics/balloons.yml`; server C does not.
2. On A, open the file, change it, click **Save**, then **Batch save…**.
3. Select B and C, click **Save to 2 servers**.
4. Expect: B `✓ Saved`, C `– Skipped: file not found`, amber banner "Saved to 1 of 2. 1 skipped, 0 failed."
5. Open the file on B: content matches A.
6. Log in as a subuser without `file.update` on B; repeat → B `✗ Error: no permission`.
