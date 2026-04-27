import React, { createContext, useContext, useMemo } from 'react';
import { useApi } from '../hooks/useApi';
import { endpoints } from '../api/endpoints';

const BrandingContext = createContext();

export function BrandingProvider({ children }) {
    const { data: branding, loading, reload } = useApi(endpoints.branding);

    const value = useMemo(() => ({
        branding: branding || {
            app_name: 'PharmaNP',
            currency_symbol: 'Rs.',
            calendar_type: 'ad',
            country_code: 'NP',
            layout: 'vertical'
        },
        loading,
        reload
    }), [branding, loading, reload]);

    return (
        <BrandingContext.Provider value={value}>
            {children}
        </BrandingContext.Provider>
    );
}

export function useBranding() {
    const context = useContext(BrandingContext);
    if (!context) {
        throw new Error('useBranding must be used within a BrandingProvider');
    }
    return context;
}
