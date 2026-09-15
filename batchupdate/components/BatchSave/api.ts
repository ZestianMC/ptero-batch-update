import http, { httpErrorToHuman } from '@/api/http';
import type { TargetServer, TerminalState, WriteResponse } from './types';

export const BASE = '/api/client/extensions/batchupdate';

export async function listTargetServers(): Promise<TargetServer[]> {
    const { data } = await http.get(`${BASE}/servers`);
    return (data.data as TargetServer[]).map(({ uuid, name }) => ({ uuid, name }));
}

const isWriteResponse = (v: unknown): v is WriteResponse =>
    !!v && typeof v === 'object' && typeof (v as any).status === 'string';

const fromBody = (body: WriteResponse): TerminalState => {
    switch (body.status) {
        case 'ok':
            return { status: 'ok' };
        case 'skipped':
            return { status: 'skipped', reason: body.reason ?? 'skipped' };
        default:
            return { status: 'error', reason: body.reason ?? 'error' };
    }
};

/**
 * Writes `content` to `path` on `target` if the file exists there.
 * Resolves to a terminal state for every outcome; never rejects.
 */
export async function writeIfExists(target: TargetServer, path: string, content: string): Promise<TerminalState> {
    try {
        const { data } = await http.post(`${BASE}/servers/${target.uuid}/write-if-exists`, content, {
            params: { file: path },
            headers: { 'Content-Type': 'text/plain' },
        });
        return isWriteResponse(data) ? fromBody(data) : { status: 'error', reason: 'malformed response' };
    } catch (err: any) {
        if (!err?.response) {
            return { status: 'error', reason: 'network error' };
        }
        if (err.response.status === 429) {
            return { status: 'error', reason: 'rate limited — save to fewer servers at once' };
        }
        if (err.response.status === 403) {
            return { status: 'error', reason: 'no permission' };
        }
        const body = err.response.data;
        if (isWriteResponse(body)) {
            return fromBody(body);
        }
        return { status: 'error', reason: httpErrorToHuman(err) };
    }
}
