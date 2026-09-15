import axios from 'axios';
import http, { httpErrorToHuman } from '@/api/http';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';
import type { Permission, TargetServer, TerminalState, WriteResponse } from './types';

export const BASE = '/api/client/extensions/batchupdate';

export async function listTargetServers(permission: Permission): Promise<TargetServer[]> {
    const { data } = await http.get(`${BASE}/servers`, { params: { permission } });
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

/** Maps a failed panel request to a terminal state. Never throws. */
const fromError = (err: any): TerminalState => {
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
    // A 5xx without our JSON body never reached PHP: nginx/Cloudflare rejected it (PHP-FPM
    // busy or down). The write was not attempted, so it is safe to retry.
    if ([502, 503, 504].includes(err.response.status)) {
        return { status: 'error', reason: `panel gateway error (${err.response.status}) — retry` };
    }
    return { status: 'error', reason: httpErrorToHuman(err) };
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
    } catch (err) {
        return fromError(err);
    }
}

type ExistsResponse = { status: 'ok'; exists: boolean; directory: boolean } | { status: 'error'; reason: string };

/**
 * Uploads `files` into `directory` on `target`, the same way the panel's Upload button does:
 * directory existence is checked through this extension, then the bytes go from the browser
 * straight to Wings with a panel-signed URL. Never rejects.
 */
export async function uploadFiles(target: TargetServer, directory: string, files: File[]): Promise<TerminalState> {
    let exists: ExistsResponse;
    try {
        exists = (await http.get(`${BASE}/servers/${target.uuid}/exists`, { params: { path: directory } })).data;
    } catch (err) {
        return fromError(err);
    }
    if (exists.status !== 'ok') {
        return { status: 'error', reason: exists.reason };
    }
    if (!exists.exists || !exists.directory) {
        return { status: 'skipped', reason: 'directory not found' };
    }

    let url: string;
    try {
        url = await getFileUploadUrl(target.uuid);
    } catch (err) {
        return fromError(err);
    }

    const form = new FormData();
    files.forEach((f) => form.append('files', f));
    try {
        await axios.post(url, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            params: { directory },
        });
        return { status: 'ok' };
    } catch (err: any) {
        if (!err?.response) {
            return { status: 'error', reason: 'daemon unreachable' };
        }
        const wingsError = typeof err.response.data?.error === 'string' ? err.response.data.error : null;
        return { status: 'error', reason: wingsError ?? `daemon error: ${err.response.status}` };
    }
}
