import React, { useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import Modal, { RequiredModalProps } from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import getFileContents from '@/api/server/files/getFileContents';
import { httpErrorToHuman } from '@/api/http';
import ServerPicker from './ServerPicker';
import ResultsList from './ResultsList';
import { listTargetServers, writeIfExists } from './api';
import { runBatch } from './runBatch';
import type { TargetServer, TargetState } from './types';

interface Props extends RequiredModalProps {
    sourceUuid: string;
    path: string;
}

type Phase = 'loading' | 'pick' | 'reading' | 'running' | 'done';

export default function BatchSaveModal({ sourceUuid, path, visible, onDismissed }: Props) {
    const [phase, setPhase] = useState<Phase>('loading');
    const [servers, setServers] = useState<TargetServer[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [results, setResults] = useState<Record<string, TargetState>>({});
    const [listError, setListError] = useState<string | null>(null);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const mounted = useRef(true);
    const content = useRef('');

    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
        };
    }, []);

    const load = () => {
        setPhase('loading');
        setListError(null);
        listTargetServers()
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
    }, [visible, sourceUuid]);

    const targets = useMemo(() => servers.filter((s) => selected.has(s.uuid)), [servers, selected]);

    const run = async (list: TargetServer[]) => {
        setPhase('running');
        setResults((prev) => ({ ...prev, ...Object.fromEntries(list.map((t) => [t.uuid, { status: 'pending' as const }])) }));
        await runBatch(
            list,
            (t) => writeIfExists(t, path, content.current),
            (uuid, state) => setResults((prev) => ({ ...prev, [uuid]: state })),
            5,
        );
        if (mounted.current) setPhase('done');
    };

    const submit = async () => {
        if (phase !== 'pick' || targets.length === 0) return;
        setSubmitError(null);
        setPhase('reading');
        try {
            content.current = await getFileContents(sourceUuid, path);
        } catch (err) {
            if (!mounted.current) return;
            setSubmitError(`Could not read the saved file: ${httpErrorToHuman(err)}`);
            setPhase('pick');
            return;
        }
        if (!mounted.current) return;
        setResults({});
        await run(targets);
    };

    const failed = useMemo(() => targets.filter((t) => results[t.uuid]?.status === 'error'), [targets, results]);

    const busy = phase === 'reading' || phase === 'running';

    return (
        <Modal visible={visible} onDismissed={onDismissed} dismissable={!busy} closeOnBackground={!busy} closeOnEscape={!busy}>
            <h2 css={tw`text-2xl mb-2`}>Batch save</h2>
            <p css={tw`text-sm text-neutral-400 mb-4`}>
                Pushes the last <strong>saved</strong> version of <code css={tw`font-mono`}>{path}</code> to the selected servers.
                Save first if you have unsaved changes. Servers without this file are skipped.
            </p>

            {phase === 'loading' && <p css={tw`text-sm text-neutral-400`}>Loading servers…</p>}

            {phase === 'reading' && <p css={tw`text-sm text-neutral-400`}>Reading saved file…</p>}

            {phase === 'pick' && (
                <>
                    {listError && <p css={tw`mb-3 p-3 rounded bg-red-900 text-red-200 text-sm`}>{listError}</p>}
                    {submitError && <p css={tw`mb-3 p-3 rounded bg-red-900 text-red-200 text-sm`}>{submitError}</p>}
                    {servers.length === 0 && !listError && (
                        <p css={tw`text-sm text-neutral-400`}>No other servers you can write files on.</p>
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
                        <Button color={'green'} disabled={targets.length === 0} onClick={submit}>
                            Save to {targets.length} server{targets.length === 1 ? '' : 's'}
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
