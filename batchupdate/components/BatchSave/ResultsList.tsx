import React from 'react';
import tw from 'twin.macro';
import Spinner from '@/components/elements/Spinner';
import type { TargetServer, TargetState } from './types';

interface Props {
    servers: TargetServer[];
    results: Record<string, TargetState>;
    done: boolean;
}

const Row = ({ name, state }: { name: string; state: TargetState }) => {
    const label =
        state.status === 'pending' ? null :
        state.status === 'ok' ? <span css={tw`text-green-400`}>✓ Saved</span> :
        state.status === 'skipped' ? <span css={tw`text-neutral-400`}>– Skipped: {state.reason}</span> :
        <span css={tw`text-red-400`}>✗ Error: {state.reason}</span>;

    return (
        <li css={tw`flex items-center justify-between px-3 py-2 text-sm border-b border-neutral-700 last:border-b-0`}>
            <span css={tw`truncate mr-4`}>{name}</span>
            <span css={tw`flex-shrink-0`}>{state.status === 'pending' ? <Spinner size={'small'} /> : label}</span>
        </li>
    );
};

export default function ResultsList({ servers, results, done }: Props) {
    const counts = servers.reduce(
        (acc, s) => {
            const st = results[s.uuid]?.status ?? 'pending';
            acc[st] = (acc[st] ?? 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );
    const ok = counts.ok ?? 0;
    const skipped = counts.skipped ?? 0;
    const failed = counts.error ?? 0;
    const total = servers.length;

    return (
        <div>
            {done && (
                <div
                    css={[
                        tw`mb-3 p-3 rounded text-sm`,
                        ok === total ? tw`bg-green-900 text-green-200` : tw`bg-yellow-900 text-yellow-200`,
                    ]}
                >
                    {ok === total
                        ? `Saved to all ${total} servers.`
                        : `Saved to ${ok} of ${total}. ${skipped} skipped, ${failed} failed.`}
                </div>
            )}
            <ul css={tw`max-h-64 overflow-y-auto border border-neutral-700 rounded`}>
                {servers.map((s) => (
                    <Row key={s.uuid} name={s.name} state={results[s.uuid] ?? { status: 'pending' }} />
                ))}
            </ul>
        </div>
    );
}
