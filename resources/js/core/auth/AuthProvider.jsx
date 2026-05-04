import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Spin } from 'antd';
import { endpoints } from '../api/endpoints';
import { authMode, getApiToken, http, responseToken, setApiToken } from '../api/http';
import { appUrl, standaloneFrontend } from '../utils/url';
import { ApiLogin } from './ApiLogin';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [state, setState] = useState({ loading: true, user: null, branding: null });

    const ensureBrowserToken = useCallback(async () => {
        if (getApiToken()) {
            return;
        }

        try {
            const { data } = await http.post(endpoints.authToken, {
                device_name: standaloneFrontend ? 'PharmaNP Standalone Frontend' : 'PharmaNP Browser Session',
            });

            const token = responseToken(data);

            if (token) {
                setApiToken(token);
            }
        } catch {
            // Session auth can still be valid even if token minting is blocked.
        }
    }, []);

    const load = useCallback(async () => {
        const loadCurrentUser = async () => {
            const { data } = await http.get(endpoints.me);
            await ensureBrowserToken();
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

            if (standaloneFrontend) {
                setApiToken(null);
                setState({ loading: false, user: null, branding: null });

                return;
            }

            window.location.href = appUrl('/login');
        }
    }, [ensureBrowserToken]);

    const login = useCallback(async (payload) => {
        if (authMode === 'session') {
            await http.get(endpoints.csrfCookie);
        }

        const { data } = await http.post(endpoints.authLogin, {
            ...payload,
            issue_token: true,
            device_name: standaloneFrontend ? 'PharmaNP Standalone Frontend' : 'PharmaNP Browser Session',
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
            if (standaloneFrontend) {
                setState({ loading: false, user: null, branding: null });
            } else {
                window.location.href = appUrl('/login');
            }
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

    if (! state.user && standaloneFrontend) {
        return <ApiLogin onLogin={login} />;
    }

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}
