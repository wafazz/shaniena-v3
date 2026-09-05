import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';

const appName = process.env.VITE_APP_NAME || 'Shaniena';

/**
 * Server-side rendering for the storefront only.
 *
 * SSR exists here for SEO and first-paint on shop pages. The admin console is
 * behind a login and indexed by nobody, so pulling CoreUI and every admin page
 * into the SSR bundle would only slow the server down.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} — ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/Shop/**/*.vue', { eager: true });

            return pages[`./Pages/${name}.vue`];
        },
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin);
        },
    }),
);
