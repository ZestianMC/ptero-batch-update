# Batch File Update (Blueprint extension)

Adds a **Batch save** button below the Pterodactyl file editor. It pushes the
last *saved* version of the open file to the same path on every server you
select. Servers where the file does not exist are skipped and reported.

## Install

1. Copy this `batchupdate/` folder to your panel root as `.blueprint/dev`
   (or zip its contents as `batchupdate.blueprint` and place it in the panel root).
2. `blueprint -install batchupdate` (or `blueprint -build` when developing from `.blueprint/dev`).

## Use

Open any file in the editor, click **Save**, then **Batch save**, pick targets,
click **Save to N servers**. Results show per server: saved, skipped (file not
found), or an error with its reason.

## Development

- PHP unit tests: see `tests/php/README` section in the repo root README.
- TS tests: `cd tests/ts && npm install && npm test`.
