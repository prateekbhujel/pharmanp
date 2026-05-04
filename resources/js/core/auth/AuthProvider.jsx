import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Spin } from 'antd';
import { endpoints } from '../api/endpoints';
import { authMode, http, setApiToken } from '../api/http';
import { appUrl, standaloneFrontend } from '../utils/url';
import { ApiLogin } from './ApiLogin';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [state, setState] = useState({ loading: true, user: null, branding: null });

    const load = useCallback(async () => {
        try {
            const { data } = await http.get(endpoints.me);
            setState({ loading: false, user: data.data, branding: data.branding });
        } catch {
            if (standaloneFrontend) {
                setState({ loading: false, user: null, branding: null });

                return;
            }

            window.location.href = appUrl('/login');
        }
    }, []);

    const login = useCallback(async (payload) => {
        if (authMode === 'session') {
            await http.get(endpoints.csrfCookie);
        }

        const { data } = await http.post(endpoints.authLogin, {
            ...payload,
            issue_token: authMode !== 'session',
            device_name: 'PharmaNP Frontend',
        });

        if (data.token) {
            setApiToken(data.token);
        }

        await load();
    }, [load]);

    const logout = useCallback(async () => {
        try {
            await http.post(endpoints.authLogout);
        } finally {
            setApiToken(null);
            if (standaloneFrontend) {
                setState({ loading: false, user: null, branding: null });
            } else {
                window.location.href = appUrl('/login');
            }
        }
    }, []);

    useEffect(() => {
        load();
    }, []);

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

    if (! state.user && standaloneFrontend) {
        return <ApiLogin onLogin={login} />;
    }

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}
