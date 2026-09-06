import '../sass/storefront.scss';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { reveal } from './Storefront/reveal';

const appName = import.meta.env.VITE_APP_NAME || 'Shaniena';

// The storefront resolves only Shop/* pages, so the admin bundle never gets
// pulled into a customer's download.
createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/Shop/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', reveal)
            .mount(el);
    },
    progress: {
        color: '#ca1515',
    },
});

// Register the service worker after load so it never competes with the first
// paint. Nothing is cached in dev, where the worker would mask rebuilds.
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // A failed registration is not worth bothering the shopper with.
        });
    });
}
