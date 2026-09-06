<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import QuantityStepper from './QuantityStepper.vue';
import { useCart } from './useCart';

/**
 * The basket, in a panel over whatever page you were on.
 *
 * Adding something used to change one number in the top-right corner of a page
 * that had not visibly moved, and the only way to check what was in the basket
 * was to leave the product you were reading. The drawer answers "what did that
 * do?" without costing the shopper their place.
 *
 * It is a view of the same data the cart page renders, from the same endpoint,
 * scoped to the same cookie — no state of its own that could disagree with the
 * server.
 */
const { cart, close, setQuantity, remove } = useCart();

const panel = ref(null);
const removing = ref(null);

const items = computed(() => cart.summary?.items ?? []);
const currency = computed(() => cart.summary?.currency ?? 'MYR');

const money = (value) => Number(value ?? 0).toLocaleString('en-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
    }
}

function removeLine(line) {
    // Held for the length of the exit transition so the row leaves rather than
    // vanishing under the pointer.
    removing.value = line.id;
    setTimeout(() => {
        remove(line);
        removing.value = null;
    }, 180);
}

watch(() => cart.open, (open) => {
    document.body.classList.toggle('drawer-open', open);

    if (open) {
        document.addEventListener('keydown', onKeydown);
        // Focus moves into the panel so the keyboard is where the eye is, and
        // Escape closes it from anywhere.
        requestAnimationFrame(() => panel.value?.focus());
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('drawer-open');
});
</script>

<template>
    <div>
        <div v-show="cart.open" class="drawer-backdrop" :class="{ 'is-open': cart.open }"
            @click="close"></div>

        <aside ref="panel" class="cart-drawer" :class="{ 'is-open': cart.open }" tabindex="-1"
            role="dialog" aria-modal="true" aria-label="Your basket" :aria-hidden="!cart.open">
            <div class="cart-drawer__head">
                <h6>Your basket</h6>
                <button type="button" aria-label="Close basket" @click="close">&times;</button>
            </div>

            <div class="cart-drawer__body">
                <!-- Skeleton rows, not a spinner: the panel keeps the shape of
                     what is about to arrive. -->
                <template v-if="cart.loading && !items.length">
                    <div v-for="n in 2" :key="n" class="cart-drawer__line">
                        <div class="cart-drawer__thumb skeleton"></div>
                        <div style="width: 100%">
                            <div class="skeleton" style="height: 12px; width: 70%"></div>
                            <div class="skeleton" style="height: 10px; width: 40%; margin-top: 10px"></div>
                        </div>
                    </div>
                </template>

                <p v-else-if="cart.error" class="cart-drawer__empty">{{ cart.error }}</p>

                <div v-else-if="!items.length" class="cart-drawer__empty">
                    <p class="mb-3">Nothing in here yet.</p>
                    <Link href="/" class="site-btn" @click="close">Start shopping</Link>
                </div>

                <template v-else>
                <div v-for="line in items" :key="line.id" class="cart-drawer__line"
                    :class="{ 'is-leaving': removing === line.id }">
                    <div class="cart-drawer__thumb"
                        :style="line.image ? { backgroundImage: `url(${line.image})` } : null"></div>

                    <div>
                        <Link v-if="line.slug" :href="`/product/${line.slug}`" class="cart-drawer__name"
                            @click="close">{{ line.name }}</Link>
                        <span v-else class="cart-drawer__name">{{ line.name }}</span>

                        <p v-if="line.variant" class="cart-drawer__variant">{{ line.variant }}</p>

                        <QuantityStepper :model-value="line.quantity" :max="line.max_purchase"
                            :label="`Quantity for ${line.name}`"
                            @update:model-value="(q) => setQuantity(line, q)" />
                    </div>

                    <div class="text-end">
                        <div class="cart-drawer__money">{{ currency }} {{ money(line.line_total) }}</div>
                        <button type="button" class="cart-drawer__remove mt-2"
                            :aria-label="`Remove ${line.name}`" @click="removeLine(line)">Remove</button>
                    </div>
                </div>
                </template>
            </div>

            <div v-if="items.length" class="cart-drawer__foot">
                <div class="cart-drawer__total">
                    <span>Subtotal</span>
                    <b>{{ currency }} {{ money(cart.summary?.subtotal) }}</b>
                </div>
                <p class="cart-drawer__note">Postage and any COD charge are worked out at checkout.</p>

                <Link href="/checkout" class="site-btn w-100 text-center d-block" @click="close">Checkout</Link>
                <Link href="/cart" class="d-block text-center mt-3" @click="close">View full basket</Link>
            </div>
        </aside>
    </div>
</template>
