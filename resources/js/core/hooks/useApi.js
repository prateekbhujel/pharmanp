import { useCallback, useEffect, useState } from 'react';
import { http } from '../api/http';

export function useApi(url) {
    const [state, setState] = useState({ loading: true, data: null, error: null });

    const load = useCallback((isMounted = () => true) => {
        setState((current) => ({ ...current, loading: true, error: null }));

        return http.get(url)
            .then(({ data }) => isMounted() && setState({ loading: false, data: data.data, error: null }))
            .catch((error) => isMounted() && setState({ loading: false, data: null, error }));
    }, [url]);

    useEffect(() => {
        let mounted = true;
        load(() => mounted);

        return () => {
            mounted = false;
        };
    }, [load]);

    return { ...state, reload: () => load(() => true) };
}
