// vite.backend.config.js

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/dashboard/admin-theme/app.css',
                'resources/js/dashboard/admin-theme/app.js',
            ],
            refresh: true,

        }),
    ],
    build: {
        outDir: 'public/build-backend',
    },
});
