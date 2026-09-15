import React, { useEffect, useMemo, useState } from 'react';
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

type Phase = 'loading' | 'pick' | 'running' | 'done';

export default function BatchSaveModal({ sourceUuid, path, visible, onDismissed }: Props) {
    const [phase, setPhase] = useState<Phase>('loading');
    const [servers, setServers] = useState<TargetServer[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [results, setResults] = useState<Record<string, TargetState>>({});
    const [error, setError] = useState<string | null>(null);

    const load = () => {
        setPhase('loading');
        setError(null);
        listTargetServers()
            .then((list) => {
                setServers(list.filter((s) => s.uuid !== sourceUuid));
                setPhase('pick');
            })
            .catch((err) => {
                setError(httpErrorToHuman(err));
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

    const submit = async () => {
        setError(null);
        let content: string;
        try {
            content = await getFileContents(sourceUuid, path);
        } catch (err) {
            setError(`Could not read the saved file: ${httpErrorToHuman(err)}`);
            return;
        }

        setPhase('running');
        setResults(Object.fromEntries(targets.map((t) => [t.uuid, { status: 'pending' as const }])));
        await runBatch(
            targets,
            (t) => writeIfExists(t, path, content),
            (uuid, state) => setResults((prev) => ({ ...prev, [uuid]: state })),
            5,
        );
        setPhase('done');
    };

    const busy = phase === 'loading' || phase === 'running';

    return (
        <Modal visible={visible} onDismissed={onDismissed} dismissable={!busy} closeOnBackground={!busy} closeOnEscape={!busy}>
            <h2 css={tw`text-2xl mb-2`}>Batch save</h2>
            <p css={tw`text-sm text-neutral-400 mb-4`}>
                Pushes the last <strong>saved</strong> version of <code css={tw`font-mono`}>{path}</code> to the selected servers.
                Save first if you have unsaved changes. Servers without this file are skipped.
            </p>

            {error && <p css={tw`mb-3 p-3 rounded bg-red-900 text-red-200 text-sm`}>{error}</p>}

            {phase === 'loading' && <p css={tw`text-sm text-neutral-400`}>Loading servers…</p>}

            {phase === 'pick' && (
                <>
                    {servers.length === 0 && !error && (
                        <p css={tw`text-sm text-neutral-400`}>No other servers you can write files on.</p>
                    )}
                    {servers.length > 0 && <ServerPicker servers={servers} selected={selected} onChange={setSelected} />}
                    <div css={tw`flex justify-end mt-4`}>
                        {error && (
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
                        <Button onClick={onDismissed} disabled={phase === 'running'}>
                            Close
                        </Button>
                    </div>
                </>
            )}
        </Modal>
    );
}
