import type { TargetServer, TerminalState } from './types';

export type WriteFn = (target: TargetServer) => Promise<TerminalState>;

const toReason = (err: unknown): string =>
    err instanceof Error ? err.message : typeof err === 'string' ? err : String(err);

/**
 * Runs `write` for every target with at most `concurrency` in flight.
 * Never rejects: a throwing `write` becomes an error row for that target.
 * Resolves once every target has a terminal state.
 */
export async function runBatch(
    targets: TargetServer[],
    write: WriteFn,
    onUpdate: (uuid: string, state: TerminalState) => void,
    concurrency = 5,
): Promise<Record<string, TerminalState>> {
    const results: Record<string, TerminalState> = {};
    const queue = [...targets];

    const worker = async (): Promise<void> => {
        for (let next = queue.shift(); next; next = queue.shift()) {
            let state: TerminalState;
            try {
                state = await write(next);
            } catch (err) {
                state = { status: 'error', reason: toReason(err) };
            }
            results[next.uuid] = state;
            onUpdate(next.uuid, state);
        }
    };

    const size = Math.max(1, Math.min(concurrency, targets.length));
    await Promise.all(Array.from({ length: size }, worker));

    return results;
}
