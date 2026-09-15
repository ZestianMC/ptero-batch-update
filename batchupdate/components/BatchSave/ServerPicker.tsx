import React, { useMemo, useState } from 'react';
import tw from 'twin.macro';
import Input from '@/components/elements/Input';
import type { TargetServer } from './types';

interface Props {
    servers: TargetServer[];
    selected: Set<string>;
    onChange: (next: Set<string>) => void;
}

export default function ServerPicker({ servers, selected, onChange }: Props) {
    const [query, setQuery] = useState('');

    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? servers.filter((s) => s.name.toLowerCase().includes(q)) : servers;
    }, [servers, query]);

    const allVisibleSelected = visible.length > 0 && visible.every((s) => selected.has(s.uuid));

    const toggle = (uuid: string) => {
        const next = new Set(selected);
        next.has(uuid) ? next.delete(uuid) : next.add(uuid);
        onChange(next);
    };

    const toggleAllVisible = () => {
        const next = new Set(selected);
        visible.forEach((s) => (allVisibleSelected ? next.delete(s.uuid) : next.add(s.uuid)));
        onChange(next);
    };

    return (
        <div>
            <Input
                type={'text'}
                placeholder={'Search servers…'}
                value={query}
                onChange={(e) => setQuery(e.currentTarget.value)}
                css={tw`mb-3`}
            />
            <label css={tw`flex items-center text-sm text-neutral-300 mb-2 cursor-pointer select-none`}>
                <input
                    type={'checkbox'}
                    checked={allVisibleSelected}
                    disabled={visible.length === 0}
                    onChange={toggleAllVisible}
                    css={tw`mr-2`}
                />
                Select all{query ? ' (matching)' : ''} ({visible.length})
            </label>
            <div css={tw`max-h-64 overflow-y-auto border border-neutral-700 rounded`}>
                {visible.length === 0 ? (
                    <p css={tw`p-3 text-sm text-neutral-400`}>No servers match.</p>
                ) : (
                    visible.map((s) => (
                        <label
                            key={s.uuid}
                            css={tw`flex items-center px-3 py-2 text-sm cursor-pointer hover:bg-neutral-700 select-none`}
                        >
                            <input type={'checkbox'} checked={selected.has(s.uuid)} onChange={() => toggle(s.uuid)} css={tw`mr-2`} />
                            <span css={tw`truncate`}>{s.name}</span>
                        </label>
                    ))
                )}
            </div>
        </div>
    );
}
