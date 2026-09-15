import React, { useState } from 'react';
import tw from 'twin.macro';
import { RequiredModalProps } from '@/components/elements/Modal';
import Input from '@/components/elements/Input';
import BatchModal from './BatchModal';
import { createDirectory, joinPath } from './api';

interface Props extends RequiredModalProps {
    sourceUuid: string;
    /** Directory currently open in the file manager; the new folder is created inside it. */
    directory: string;
}

/** A single path segment: no slashes, no ".", no "..". */
const isValidName = (name: string) => name.length > 0 && !name.includes('/') && !name.includes('\\') && name !== '.' && name !== '..';

/** Creates one folder inside `directory` on every selected server that has that directory. */
export default function BatchMkdirModal({ sourceUuid, directory, visible, onDismissed }: Props) {
    const [folderName, setFolderName] = useState('');
    const trimmed = folderName.trim();

    return (
        <BatchModal
            visible={visible}
            onDismissed={onDismissed}
            sourceUuid={sourceUuid}
            permission={'file.create'}
            title={'Batch new folder'}
            description={
                <>
                    Creates <code css={tw`font-mono`}>{joinPath(directory, trimmed || '…')}</code> on the selected servers.
                    Servers without <code css={tw`font-mono`}>{directory}</code> are skipped, as are servers where it already exists.
                </>
            }
            canSubmit={isValidName(trimmed)}
            submitLabel={(n) => `Create on ${n} server${n === 1 ? '' : 's'}`}
            write={(t) => createDirectory(t, directory, trimmed)}
        >
            <Input
                type={'text'}
                placeholder={'Folder name'}
                value={folderName}
                onChange={(e) => setFolderName(e.currentTarget.value)}
            />
        </BatchModal>
    );
}
