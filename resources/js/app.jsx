import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider, App as AntApp } from 'antd';
import 'antd/dist/reset.css';
import { AuthProvider } from './core/auth/AuthProvider';
import { AppShell } from './core/layout/AppShell';
import { SetupWizard } from './modules/setup/SetupWizard';

import { ThemeProvider, useTheme } from './core/theme/ThemeContext';
import { BrandingProvider } from './core/context/BrandingContext';
import { buildAntdTheme } from './core/theme/antdTheme';

const rootElement = document.getElementById('pharmanp-root');
const mode = rootElement?.dataset.appMode || 'app';

function RootApp() {
    const { colorPrimary } = useTheme();

    return (
        <ConfigProvider theme={buildAntdTheme(colorPrimary)} select={{ showSearch: true }}>
            <AntApp>
                <BrandingProvider>
                    {mode === 'setup' ? (
                        <SetupWizard />
                    ) : (
                        <AuthProvider>
                            <AppShell />
                        </AuthProvider>
                    )}
                </BrandingProvider>
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
