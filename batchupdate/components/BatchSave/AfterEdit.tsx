import React, { useState } from 'react';
import tw from 'twin.macro';
import { useLocation, useParams } from 'react-router';
import Button from '@/components/elements/Button';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { hashToPath } from '@/helpers';
import BatchSaveModal from './BatchSaveModal';

/**
 * Blueprint slot: Server.Files.Edit.AfterEdit. Renders directly under the
 * native save row. Hidden on /files/new (nothing saved yet to push).
 */
export default function AfterEdit() {
    const { action } = useParams<{ action: 'new' | string }>();
    const { hash } = useLocation();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [open, setOpen] = useState(false);

    if (action !== 'edit') {
        return null;
    }

    const path = hashToPath(hash);

    return (
        <Can action={'file.update'}>
            <div css={tw`flex justify-end mt-2`}>
                <Button isSecondary onClick={() => setOpen(true)}>
                    Batch save…
                </Button>
            </div>
            {open && <BatchSaveModal visible={open} onDismissed={() => setOpen(false)} sourceUuid={uuid} path={path} />}
        </Can>
    );
}
