import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';

// Blueprint's install step runs sed over every extension file and replaces these tokens
// with extension metadata / panel paths. A JSX expression like `{name}` would be rewritten
// into the extension's display name. Keep this list in sync with
// scripts/commands/extensions/install.sh in BlueprintFramework/framework.
const PLACEHOLDERS = [
    '{identifier}', '{name}', '{author}', '{version}', '{random}', '{timestamp}', '{mode}',
    '{target}', '{root}', '{webroot}', '{viewcontext}', '{appcontext}', '{engine}', '{fs}',
    '{root/public}', '{root/data}', '{root/fs}', '{webroot/public}', '{webroot/fs}',
    '{fs/private}', '{is_target}',
];

const EXT_DIR = join(import.meta.dirname, '..', '..', 'batchupdate');

const walk = (dir: string): string[] =>
    readdirSync(dir, { withFileTypes: true }).flatMap((e) =>
        e.isDirectory() ? walk(join(dir, e.name)) : [join(dir, e.name)],
    );

test('no extension file contains a Blueprint placeholder token', () => {
    const offenders: string[] = [];
    for (const file of walk(EXT_DIR)) {
        const text = readFileSync(file, 'utf8');
        for (const token of PLACEHOLDERS) {
            if (text.includes(token)) offenders.push(`${relative(EXT_DIR, file)}: ${token}`);
        }
    }
    assert.deepEqual(offenders, []);
});
