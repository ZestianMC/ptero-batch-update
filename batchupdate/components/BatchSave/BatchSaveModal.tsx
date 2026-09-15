import React, { useRef } from 'react';
import tw from 'twin.macro';
import { RequiredModalProps } from '@/components/elements/Modal';
import getFileContents from '@/api/server/files/getFileContents';
import { httpErrorToHuman } from '@/api/http';
import BatchModal from './BatchModal';
import { writeIfExists } from './api';

interface Props extends RequiredModalProps {
    sourceUuid: string;
    path: string;
}

/** Pushes the last saved version of `path` on the current server to the same path elsewhere. */
export default function BatchSaveModal({ sourceUuid, path, visible, onDismissed }: Props) {
    const content = useRef('');

    const prepare = async () => {
        try {
            content.current = await getFileContents(sourceUuid, path);
        } catch (err) {
            throw new Error(`Could not read the saved file: ${httpErrorToHuman(err)}`);
        }
    };

    return (
        <BatchModal
            visible={visible}
            onDismissed={onDismissed}
            sourceUuid={sourceUuid}
            permission={'file.update'}
            title={'Batch save'}
            description={
                <>
                    Pushes the last <strong>saved</strong> version of <code css={tw`font-mono`}>{path}</code> to the
                    selected servers. Save first if you have unsaved changes. Servers without this file are skipped.
                </>
            }
            prepare={prepare}
            write={(t) => writeIfExists(t, path, content.current)}
        />
    );
}
