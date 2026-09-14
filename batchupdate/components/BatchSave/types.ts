export interface TargetServer {
    uuid: string;
    name: string;
}

export type TargetState =
    | { status: 'pending' }
    | { status: 'ok' }
    | { status: 'skipped'; reason: string }
    | { status: 'error'; reason: string };

export type TerminalState = Exclude<TargetState, { status: 'pending' }>;

/** Body of the write-if-exists endpoint, on any HTTP status. */
export interface WriteResponse {
    status: 'ok' | 'skipped' | 'error';
    reason?: string;
}
