import React, { ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import Modal, { RequiredModalProps } from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import { httpErrorToHuman } from '@/api/http';
import ServerPicker from './ServerPicker';
import ResultsList from './ResultsList';
import { listTargetServers } from './api';
import { runBatch } from './runBatch';
import type { Permission, TargetServer, TargetState, TerminalState } from './types';

const CONCURRENCY = 5;

interface Props extends RequiredModalProps {
    /** Server the user is looking at; excluded from the target list. */
    sourceUuid: string;
    /** Permission each target must grant; also filters the list. */
    permission: Permission;
    title: string;
    description: ReactNode;
    /** Extra inputs shown above the server picker (e.g. a file input). */
    children?: ReactNode;
    /** False disables the submit button (e.g. no file chosen yet). */
    canSubmit?: boolean;
    /** Runs once before the batch (e.g. read the saved file). Throw to abort with a message. */
    prepare?: () => Promise<void>;
    /** Performs the action on one target. Must resolve to a terminal state, never reject. */
    write: (target: TargetServer) => Promise<TerminalState>;
    submitLabel?: (count: number) => string;
}

type Phase = 'loading' | 'pick' | 'preparing' | 'running' | 'done';

/**
 * Shared shell for every batch action: loads the permission-filtered server list, lets the
 * user pick targets, runs `write` on each with bounded concurrency and shows live results.
 */
export default function BatchModal({
    sourceUuid, permission, title, description, children, canSubmit = true, prepare, write, submitLabel,
    visible, onDismissed,
}: Props) {
    const [phase, setPhase] = useState<Phase>('loading');
    const [servers, setServers] = useState<TargetServer[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [results, setResults] = useState<Record<string, TargetState>>({});
    const [listError, setListError] = useState<string | null>(null);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const mounted = useRef(true);

    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
        };
    }, []);

    const load = () => {
        setPhase('loading');
        setListError(null);
        listTargetServers(permission)
            .then((list) => {
                if (!mounted.current) return;
                setServers(list.filter((s) => s.uuid !== sourceUuid));
                setPhase('pick');
            })
            .catch((err) => {
                if (!mounted.current) return;
                setListError(httpErrorToHuman(err));
                setPhase('pick');
            });
    };

    useEffect(() => {
        if (!visible) return;
        setSelected(new Set());
        setResults({});
        load();
    }, [visible, sourceUuid, permission]);

    const targets = useMemo(() => servers.filter((s) => selected.has(s.uuid)), [servers, selected]);

    const run = async (list: TargetServer[]) => {
        setPhase('running');
        setResults((prev) => ({ ...prev, ...Object.fromEntries(list.map((t) => [t.uuid, { status: 'pending' as const }])) }));
        await runBatch(list, write, (uuid, state) => setResults((prev) => ({ ...prev, [uuid]: state })), CONCURRENCY);
        if (mounted.current) setPhase('done');
    };

    const submit = async () => {
        if (phase !== 'pick' || targets.length === 0 || !canSubmit) return;
        setSubmitError(null);
        if (prepare) {
            setPhase('preparing');
            try {
                await prepare();
            } catch (err) {
                if (!mounted.current) return;
                setSubmitError(err instanceof Error ? err.message : String(err));
                setPhase('pick');
                return;
            }
            if (!mounted.current) return;
        }
        setResults({});
        await run(targets);
    };

    const failed = useMemo(() => targets.filter((t) => results[t.uuid]?.status === 'error'), [targets, results]);
    const busy = phase === 'preparing' || phase === 'running';
    const label = submitLabel ?? ((n: number) => `Save to ${n} server${n === 1 ? '' : 's'}`);

    return (
        <Modal visible={visible} onDismissed={onDismissed} dismissable={!busy} closeOnBackground={!busy} closeOnEscape={!busy}>
            <h2 css={tw`text-2xl mb-2`}>{title}</h2>
            <div css={tw`text-sm text-neutral-400 mb-4`}>{description}</div>

            {phase === 'loading' && <p css={tw`text-sm text-neutral-400`}>Loading servers…</p>}
            {phase === 'preparing' && <p css={tw`text-sm text-neutral-400`}>Preparing…</p>}

            {phase === 'pick' && (
                <>
                    {listError && <p css={tw`mb-3 p-3 rounded bg-red-900 text-red-200 text-sm`}>{listError}</p>}
                    {submitError && <p css={tw`mb-3 p-3 rounded bg-red-900 text-red-200 text-sm`}>{submitError}</p>}
                    {children && <div css={tw`mb-4`}>{children}</div>}
                    {servers.length === 0 && !listError && (
                        <p css={tw`text-sm text-neutral-400`}>No other servers you can do this on.</p>
                    )}
                    {servers.length > 0 && <ServerPicker servers={servers} selected={selected} onChange={setSelected} />}
                    <div css={tw`flex justify-end mt-4`}>
                        {listError && (
                            <Button isSecondary onClick={load} css={tw`mr-2`}>
                                Retry
                            </Button>
                        )}
                        <Button isSecondary onClick={onDismissed} css={tw`mr-2`}>
                            Cancel
                        </Button>
                        <Button color={'green'} disabled={targets.length === 0 || !canSubmit} onClick={submit}>
                            {label(targets.length)}
                        </Button>
                    </div>
                </>
            )}

            {(phase === 'running' || phase === 'done') && (
                <>
                    <ResultsList servers={targets} results={results} done={phase === 'done'} />
                    <div css={tw`flex justify-end mt-4`}>
                        {phase === 'done' && failed.length > 0 && (
                            <Button isSecondary onClick={() => run(failed)} css={tw`mr-2`}>
                                Retry failed ({failed.length})
                            </Button>
                        )}
                        <Button onClick={onDismissed} disabled={phase === 'running'}>
                            Close
                        </Button>
                    </div>
                </>
            )}
        </Modal>
    );
}
