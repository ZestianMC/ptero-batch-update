import React, { useState } from 'react';
import tw from 'twin.macro';
import Modal from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import { ServerContext } from '@/state/server';
import BatchUploadModal from './BatchUploadModal';
import BatchMkdirModal from './BatchMkdirModal';

type Open = 'menu' | 'upload' | 'mkdir' | null;

/**
 * Blueprint slot: Server.Files.Browse.FileButtons. Rendered by the panel inside its
 * `Can file.create` block next to the New Directory / Upload buttons. One button opens a
 * chooser; the chosen operation opens its own batch modal.
 */
export default function FileButtons() {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const [open, setOpen] = useState<Open>(null);
    const close = () => setOpen(null);

    return (
        <>
            <Button isSecondary onClick={() => setOpen('menu')} css={tw`mr-4`}>
                Batch operations…
            </Button>

            {open === 'menu' && (
                <Modal visible onDismissed={close}>
                    <h2 css={tw`text-2xl mb-2`}>Batch operations</h2>
                    <p css={tw`text-sm text-neutral-400 mb-4`}>
                        Applies to <code css={tw`font-mono`}>{directory}</code> on the servers you pick next.
                    </p>
                    <div css={tw`flex flex-col space-y-3`}>
                        <Button onClick={() => setOpen('upload')}>Upload files…</Button>
                        <Button onClick={() => setOpen('mkdir')}>New folder…</Button>
                    </div>
                    <div css={tw`flex justify-end mt-4`}>
                        <Button isSecondary onClick={close}>
                            Cancel
                        </Button>
                    </div>
                </Modal>
            )}

            {open === 'mkdir' && <BatchMkdirModal visible onDismissed={close} sourceUuid={uuid} directory={directory} />}
            {open === 'upload' && <BatchUploadModal visible onDismissed={close} sourceUuid={uuid} directory={directory} />}
        </>
    );
}
