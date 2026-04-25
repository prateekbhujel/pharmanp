import { useEffect, useState } from 'react';
import { http } from '../api/http';

export function useApi(url) {
    const [state, setState] = useState({ loading: true, data: null, error: null });

    useEffect(() => {
        let mounted = true;
        setState({ loading: true, data: null, error: null });

        http.get(url)
            .then(({ data }) => mounted && setState({ loading: false, data: data.data, error: null }))
            .catch((error) => mounted && setState({ loading: false, data: null, error }));

        return () => {
            mounted = false;
        };
    }, [url]);

    return state;
}
