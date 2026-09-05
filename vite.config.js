import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            // Two bundles on purpose: the shop must not ship CoreUI, and the
            // console must not ship Ashion.
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/sass/storefront.scss',
                'resources/js/storefront.js',
            ],
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
