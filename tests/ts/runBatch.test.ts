import { test } from 'node:test';
import assert from 'node:assert/strict';
import { runBatch } from '../../batchupdate/components/BatchSave/runBatch';
import type { TargetServer, TerminalState } from '../../batchupdate/components/BatchSave/types';

const servers = (n: number): TargetServer[] =>
    Array.from({ length: n }, (_, i) => ({ uuid: `uuid-${i}`, name: `srv${i}` }));

const defer = () => {
    let resolve!: (v: TerminalState) => void;
    let reject!: (e: unknown) => void;
    const promise = new Promise<TerminalState>((res, rej) => { resolve = res; reject = rej; });
    return { promise, resolve, reject };
};

test('all ok: every target reported once, result map complete', async () => {
    const updates: [string, TerminalState][] = [];
    const result = await runBatch(servers(3), async () => ({ status: 'ok' }), (u, s) => updates.push([u, s]));
    assert.deepEqual(result, { 'uuid-0': { status: 'ok' }, 'uuid-1': { status: 'ok' }, 'uuid-2': { status: 'ok' } });
    assert.deepEqual(updates.map(([u]) => u).sort(), ['uuid-0', 'uuid-1', 'uuid-2']);
});

test('mixed outcomes are passed through unchanged', async () => {
    const write = async (t: TargetServer): Promise<TerminalState> =>
        t.uuid === 'uuid-1' ? { status: 'skipped', reason: 'file not found' } :
        t.uuid === 'uuid-2' ? { status: 'error', reason: 'no permission' } : { status: 'ok' };
    const result = await runBatch(servers(3), write, () => undefined);
    assert.deepEqual(result['uuid-1'], { status: 'skipped', reason: 'file not found' });
    assert.deepEqual(result['uuid-2'], { status: 'error', reason: 'no permission' });
});

test('a rejected write becomes an error row and never rejects the pool', async () => {
    const write = async (t: TargetServer): Promise<TerminalState> => {
        if (t.uuid === 'uuid-0') throw new Error('network error');
        return { status: 'ok' };
    };
    const result = await runBatch(servers(2), write, () => undefined);
    assert.deepEqual(result['uuid-0'], { status: 'error', reason: 'network error' });
    assert.deepEqual(result['uuid-1'], { status: 'ok' });
});

test('non-Error rejection is stringified', async () => {
    const result = await runBatch(servers(1), async () => { throw 'boom'; }, () => undefined);
    assert.deepEqual(result['uuid-0'], { status: 'error', reason: 'boom' });
});

test('never more than `concurrency` writes in flight', async () => {
    const pending = new Map<string, ReturnType<typeof defer>>();
    let inFlight = 0;
    let maxInFlight = 0;
    const write = (t: TargetServer) => {
        inFlight++;
        maxInFlight = Math.max(maxInFlight, inFlight);
        const d = defer();
        pending.set(t.uuid, d);
        return d.promise.finally(() => { inFlight--; });
    };

    const done = runBatch(servers(12), write, () => undefined, 5);
    await new Promise((r) => setTimeout(r, 0));
    assert.equal(pending.size, 5);

    // release all, in an arbitrary order
    while (pending.size) {
        const [uuid, d] = pending.entries().next().value!;
        pending.delete(uuid);
        d.resolve({ status: 'ok' });
        await new Promise((r) => setTimeout(r, 0));
    }
    const result = await done;
    assert.equal(Object.keys(result).length, 12);
    assert.equal(maxInFlight, 5);
});

test('empty target list resolves to an empty map', async () => {
    assert.deepEqual(await runBatch([], async () => ({ status: 'ok' }), () => undefined), {});
});

test('concurrency below 1 is clamped to 1', async () => {
    let inFlight = 0, max = 0;
    const write = async () => { inFlight++; max = Math.max(max, inFlight); await new Promise((r) => setTimeout(r, 1)); inFlight--; return { status: 'ok' } as const; };
    await runBatch(servers(3), write, () => undefined, 0);
    assert.equal(max, 1);
});
