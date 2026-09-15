# Batch File Update (Blueprint extension)

Adds two buttons to the Pterodactyl file manager:

- **Batch save** (below the file editor): pushes the last *saved* version of the open
  file to the same path on every server you select. Servers where the file does not
  exist are skipped and reported.
- **Batch upload** (next to Upload in the file list): uploads the chosen file(s) into
  the open directory on every server you select. Servers without that directory are
  skipped; existing files with the same name are overwritten.
- **Batch new folder** (same row): creates a folder inside the open directory on every
  server you select. Servers without that directory, or where it already exists, are skipped.

## Install

1. Copy this `batchupdate/` folder to your panel root as `.blueprint/dev`
   (or zip its contents as `batchupdate.blueprint` and place it in the panel root).
2. `blueprint -install batchupdate` (or `blueprint -build` when developing from `.blueprint/dev`).

## Use

Batch save: open a file in the editor, click **Save**, then **Batch save…**, pick targets,
click **Save to N servers**. Needs `file.update` on each target.

Batch upload: open the destination directory, click **Batch upload…**, choose files, pick
targets, click **Upload to N servers**. Needs `file.create` on each target. Bytes go from the
browser straight to each server's Wings (same mechanism as the panel's Upload button).

Results show per server: saved, skipped (with reason), or an error with its reason; failed
targets can be retried from the results view.

The panel's client API rate limit (240 requests/min per user by default) caps a single batch
at roughly 240 servers (save) or ~80 (upload, three requests per target); larger fleets should
be done in chunks.
Batch saves are recorded in the panel log (`batchupdate.write` entries with user id, server
uuid, path and status) but do not appear in each target server's Activity tab.

## Development

- PHP unit tests and TS tests: see the repo root README.
