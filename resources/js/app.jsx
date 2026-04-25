import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider, App as AntApp } from 'antd';
import 'antd/dist/reset.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import { AuthProvider } from './core/auth/AuthProvider';
import { AppShell } from './core/layout/AppShell';
import { SetupWizard } from './modules/setup/SetupWizard';

const rootElement = document.getElementById('pharmanp-root');
const mode = rootElement?.dataset.appMode || 'app';

const theme = {
    token: {
        colorPrimary: '#0f766e',
        borderRadius: 8,
        fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        colorBgLayout: '#eef2f7',
        colorBorderSecondary: '#dbe3ee',
    },
    components: {
        Card: { borderRadiusLG: 8 },
        Button: { borderRadius: 8, controlHeight: 38 },
        Drawer: { borderRadiusLG: 14 },
        Modal: { borderRadiusLG: 16 },
        Select: { controlHeight: 38 },
        Table: { headerBg: '#f8fafc', rowHoverBg: '#f1f5f9' },
    },
};

function Root() {
    return (
        <ConfigProvider theme={theme}>
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

if (rootElement) {
    createRoot(rootElement).render(<Root />);
}
