const baseTheme = {
    token: {
        fontSize: 13,
        colorPrimary: '#0f766e',
        colorInfo: '#0369a1',
        colorSuccess: '#16a34a',
        colorError: '#ef4444',
        colorWarning: '#f59e0b',
        borderRadius: 8,
        fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        boxShadow: '0 4px 16px rgba(0, 0, 0, 0.03), 0 2px 6px rgba(0, 0, 0, 0.02)',
        colorBgLayout: '#f4f7f9',
        colorText: '#1e293b',
        colorTextSecondary: '#64748b',
    },
    components: {
        Card: {
            borderRadiusLG: 12,
            boxShadowTertiary: '0 10px 25px -5px rgba(0, 0, 0, 0.02), 0 8px 10px -6px rgba(0, 0, 0, 0.01)',
            headerBg: 'transparent',
            headerFontSize: 14,
            paddingLG: 20,
        },
        Button: {
            borderRadius: 6,
            controlHeight: 34,
            fontWeight: 500,
            primaryShadow: '0 6px 14px rgba(15, 118, 110, 0.18)',
        },
        Table: {
            headerBg: '#f8fafc',
            rowHoverBg: '#f1f5f9',
            headerColor: '#475569',
            borderRadius: 8,
            padding: 12,
        },
        Input: {
            borderRadius: 6,
            controlHeight: 34,
            colorBorder: '#cbd5e1',
        },
        Select: {
            borderRadius: 6,
            controlHeight: 34,
        },
        Menu: {
            itemBorderRadius: 6,
            itemHeight: 38,
        },
    },
};

export function buildAntdTheme(colorPrimary) {
    return {
        ...baseTheme,
        token: {
            ...baseTheme.token,
            colorPrimary: colorPrimary || baseTheme.token.colorPrimary,
        },
    };
}
