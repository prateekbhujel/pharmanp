import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    root,
    envDir: root,
    base: './',
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        fs: {
            allow: [fileURLToPath(new URL('..', import.meta.url))],
        },
    },
    build: {
        outDir: '../public/frontend-build',
        emptyOutDir: true,
        chunkSizeWarningLimit: 1400,
        rollupOptions: {
            output: {
                manualChunks: {
                    antd: ['antd', '@ant-design/icons'],
                    axios: ['axios'],
                },
            },
        },
    },
});
