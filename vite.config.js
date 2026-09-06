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
                // One stylesheet per storefront theme. The root view serves
                // whichever one HQ has chosen and never both.
                'resources/sass/storefront.scss',
                'resources/sass/electro.scss',
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

        // Dev only. A theme's stylesheet is now a top-level entry chosen by the
        // server, so while `npm run dev` is running the browser fetches it from
        // Vite — and the icon-font urls inside it, which are root-relative,
        // then resolve against Vite's origin instead of the app's. Proxying the
        // template's static directory back to `artisan serve` keeps the icons
        // where the CSS expects them. Production is unaffected: there the CSS
        // is served from the app's own origin.
        proxy: {
            '/storefront': {
                target: process.env.APP_URL || 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
    },
});
