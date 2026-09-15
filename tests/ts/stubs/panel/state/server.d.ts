export const ServerContext: {
    useStoreState: <R>(
        selector: (state: {
            server: { data?: { uuid: string; id: string; name: string } };
            files: { directory: string };
        }) => R,
    ) => R;
};
