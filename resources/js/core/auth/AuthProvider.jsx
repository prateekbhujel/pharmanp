import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import Spin from 'antd/es/spin';
import { endpoints } from '../api/endpoints';
import { getApiToken, http, responseToken, setApiToken } from '../api/http';
import { ApiLogin } from './ApiLogin';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [state, setState] = useState({ loading: true, user: null, branding: null });

    const load = useCallback(async () => {
        const loadCurrentUser = async () => {
            const { data } = await http.get(endpoints.me);
            setState({ loading: false, user: data.data, branding: data.branding });
        };

        try {
            await loadCurrentUser();
        } catch (error) {
            if (error?.response?.status === 401 && getApiToken()) {
                setApiToken(null);

                try {
                    await loadCurrentUser();

                    return;
                } catch {
                    // Fall through to the normal unauthenticated state.
                }
            }

            setApiToken(null);
            setState({ loading: false, user: null, branding: null });
        }
    }, []);

    const login = useCallback(async (payload) => {
        const { data } = await http.post(endpoints.authLogin, {
            ...payload,
            device_name: 'PharmaNP React SPA',
        });

        const token = responseToken(data);

        if (token) {
            setApiToken(token);
        }

        await load();
    }, [load]);

    const logout = useCallback(async () => {
        try {
            await http.post(endpoints.authLogout);
        } finally {
            setApiToken(null);
            setState({ loading: false, user: null, branding: null });
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const value = useMemo(() => ({
        ...state,
        login,
        reload: load,
        logout,
    }), [state, login, load, logout]);

    if (state.loading) {
        return (
            <div className="screen-center">
                <Spin />
            </div>
        );
    }

    if (! state.user) {
        return <ApiLogin onLogin={login} />;
    }

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}
