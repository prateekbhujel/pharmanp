import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider, App as AntApp } from 'antd';
import 'antd/dist/reset.css';
import { AuthProvider } from './core/auth/AuthProvider';
import { AppShell } from './core/layout/AppShell';
import { SetupWizard } from './modules/setup/SetupWizard';

import { ThemeProvider, useTheme } from './core/theme/ThemeContext';

const rootElement = document.getElementById('pharmanp-root');
const mode = rootElement?.dataset.appMode || 'app';

const baseTheme = {
    token: {
        fontSize: 13,
        colorPrimary: '#0891b2',
        colorInfo: '#0ea5e9',
        colorSuccess: '#10b981',
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
        }
    },
};

function RootApp() {
    const { colorPrimary } = useTheme();
    
    const dynamicTheme = {
        ...baseTheme,
        token: {
            ...baseTheme.token,
            colorPrimary,
        }
    };

    return (
        <ConfigProvider theme={dynamicTheme}>
            <AntApp>
                {mode === 'setup' ? (
                    <SetupWizard />
                ) : (
                    <AuthProvider>
                        <AppShell />
                    </AuthProvider>
                )}
            </AntApp>
        </ConfigProvider>
    );
}

function Root() {
    return (
        <ThemeProvider>
            <RootApp />
        </ThemeProvider>
    );
}

if (rootElement) {
    createRoot(rootElement).render(<Root />);
}
