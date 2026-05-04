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
                manualChunks(id) {
                    if (id.includes('/node_modules/react') || id.includes('/node_modules/react-dom')) {
                        return 'react';
                    }

                    if (
                        id.includes('/node_modules/antd/')
                        || id.includes('/node_modules/@ant-design/icons/')
                        || id.includes('/node_modules/@rc-component/')
                        || id.includes('/node_modules/rc-')
                    ) {
                        return 'antd';
                    }

                    if (id.includes('/node_modules/axios/')) {
                        return 'axios';
                    }
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
