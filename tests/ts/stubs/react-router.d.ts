declare module 'react-router' {
    export function useParams<T extends Record<string, string | undefined>>(): T;
    export function useLocation(): { hash: string; pathname: string };
}
