import React, { useState } from 'react';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import { ServerContext } from '@/state/server';
import BatchUploadModal from './BatchUploadModal';

/**
 * Blueprint slot: Server.Files.Browse.FileButtons. Rendered by the panel inside its
 * `Can file.create` block next to the Upload / New file buttons.
 */
export default function FileButtons() {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button isSecondary onClick={() => setOpen(true)} css={tw`mr-4`}>
                Batch upload…
            </Button>
            {open && <BatchUploadModal visible={open} onDismissed={() => setOpen(false)} sourceUuid={uuid} directory={directory} />}
        </>
    );
}
