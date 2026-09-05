import * as CoreUI from '@coreui/vue';
import { CIcon } from '@coreui/icons-vue';

// @coreui/vue v5 dropped its install() plugin, so `app.use(CoreuiVue)` is a no-op
// and every <C*> tag renders as an empty comment. Register the named exports instead.
export function registerCoreUI(app) {
    for (const [name, component] of Object.entries(CoreUI)) {
        if (/^C[A-Z]/.test(name) && component && typeof component === 'object') {
            app.component(name, component);
        }
    }

    app.component('CIcon', CIcon);

    return app;
}
