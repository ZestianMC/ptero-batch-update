export const ServerContext: {
    useStoreState: <R>(selector: (state: { server: { data?: { uuid: string; id: string; name: string } } }) => R) => R;
};
