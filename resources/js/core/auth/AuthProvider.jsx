import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { Spin } from 'antd';
import { endpoints } from '../api/endpoints';
import { http } from '../api/http';
import { appUrl } from '../utils/url';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [state, setState] = useState({ loading: true, user: null, branding: null });

    useEffect(() => {
        http.get(endpoints.me)
            .then(({ data }) => setState({ loading: false, user: data.data, branding: data.branding }))
            .catch(() => {
                window.location.href = appUrl('/login');
            });
    }, []);

    const value = useMemo(() => state, [state]);

    if (state.loading) {
        return (
            <div className="screen-center">
                <Spin />
            </div>
        );
    }

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    return useContext(AuthContext);
}
