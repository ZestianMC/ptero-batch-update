import React, { useState } from 'react';
import tw from 'twin.macro';
import { RequiredModalProps } from '@/components/elements/Modal';
import BatchModal from './BatchModal';
import { uploadFiles } from './api';

interface Props extends RequiredModalProps {
    sourceUuid: string;
    /** Directory currently open in the file manager; files land there on every target. */
    directory: string;
}

/** Uploads the chosen file(s) into `directory` on every selected server that has that directory. */
export default function BatchUploadModal({ sourceUuid, directory, visible, onDismissed }: Props) {
    const [files, setFiles] = useState<File[]>([]);

    return (
        <BatchModal
            visible={visible}
            onDismissed={onDismissed}
            sourceUuid={sourceUuid}
            permission={'file.create'}
            title={'Batch upload'}
            description={
                <>
                    Uploads the chosen file(s) into <code css={tw`font-mono`}>{directory}</code> on the selected servers.
                    Existing files with the same name are overwritten. Servers without this directory are skipped.
                </>
            }
            canSubmit={files.length > 0}
            submitLabel={(n) => `Upload to ${n} server${n === 1 ? '' : 's'}`}
            write={(t) => uploadFiles(t, directory, files)}
        >
            <input
                type={'file'}
                multiple
                onChange={(e) => setFiles(Array.from(e.currentTarget.files ?? []))}
                css={tw`text-sm text-neutral-300`}
            />
            {files.length > 0 && (
                <p css={tw`mt-2 text-xs text-neutral-400`}>
                    {files.length} file{files.length === 1 ? '' : 's'}: {files.map((f) => f.name).join(', ')}
                </p>
            )}
        </BatchModal>
    );
}
