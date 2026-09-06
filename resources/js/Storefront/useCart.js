import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * The basket, shared by everything that can change it.
 *
 * The source added to the basket with a full page reload and a JavaScript
 * alert; this app replaced the alert with a line of text under the button,
 * which is still easy to miss on a long product page. One piece of state now
 * backs the header trigger, the product cards and the product page, so
 * wherever an item goes in, the same drawer opens and shows what happened.
 *
 * The count in the header is not kept here on purpose — it comes from the
 * server on every Inertia visit as a shared prop, so it cannot drift from what
 * the database holds.
 */
const state = reactive({
    open: false,
    loading: false,
    summary: null,
    error: null,
    /** Variant id currently being added, so one card can show its own spinner. */
    adding: null,
    /** Variant id that just went in, for the tick on the button. */
    justAdded: null,
});

let justAddedTimer = null;

async function refresh() {
    state.loading = true;
    state.error = null;

    try {
        const response = await fetch('/cart/summary', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        state.summary = await response.json();
    } catch {
        // The basket itself is fine — this is only the drawer's view of it.
        state.error = 'Could not load your basket just now.';
    } finally {
        state.loading = false;
    }
}

function open() {
    state.open = true;
    refresh();
}

function close() {
    state.open = false;
}

/**
 * Add a variant, then show the drawer.
 *
 * Inertia's own POST is used rather than fetch: the redirect back is what
 * refreshes the cart badge and any stock the page is showing, and it keeps
 * validation errors — the per-order cap, the last unit selling out between
 * page load and click — arriving the same way they do everywhere else.
 */
function add({ productId, variantId, quantity = 1 }, { onError } = {}) {
    if (state.adding) {
        return;
    }

    state.adding = variantId;

    router.post('/cart', {
        product_id: productId,
        variant_id: variantId,
        quantity,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            state.justAdded = variantId;
            clearTimeout(justAddedTimer);
            justAddedTimer = setTimeout(() => { state.justAdded = null; }, 2000);
            open();
        },
        onError: (errors) => {
            if (onError) {
                onError(Object.values(errors)[0] ?? 'That could not be added.');
            }
        },
        onFinish: () => { state.adding = null; },
    });
}

/** Change a line's quantity from inside the drawer. */
function setQuantity(line, quantity) {
    router.put(`/cart/${line.id}`, { quantity }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: refresh,
    });
}

function remove(line) {
    router.delete(`/cart/${line.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: refresh,
    });
}

export function useCart() {
    return { cart: state, open, close, refresh, add, setQuantity, remove };
}
