import React, { useState } from 'react';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import { ServerContext } from '@/state/server';
import BatchUploadModal from './BatchUploadModal';
import BatchMkdirModal from './BatchMkdirModal';

type Open = 'upload' | 'mkdir' | null;

/**
 * Blueprint slot: Server.Files.Browse.FileButtons. Rendered by the panel inside its
 * `Can file.create` block next to the New Directory / Upload buttons.
 */
export default function FileButtons() {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const [open, setOpen] = useState<Open>(null);
    const close = () => setOpen(null);

    return (
        <>
            <Button isSecondary onClick={() => setOpen('mkdir')} css={tw`mr-4`}>
                Batch new folder…
            </Button>
            <Button isSecondary onClick={() => setOpen('upload')} css={tw`mr-4`}>
                Batch upload…
            </Button>
            {open === 'mkdir' && <BatchMkdirModal visible onDismissed={close} sourceUuid={uuid} directory={directory} />}
            {open === 'upload' && <BatchUploadModal visible onDismissed={close} sourceUuid={uuid} directory={directory} />}
        </>
    );
}
