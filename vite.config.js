import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/styles.scss',
                'resources/js/app.js'
            ]
        }),
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/**'],
        },
    },
});
