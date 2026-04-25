import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
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
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
