<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCart } from '../../useCart';

/**
 * Electro's product tile.
 *
 * A bordered card with the price and the button always visible, rather than
 * Ashion's rail that rises on hover — this theme is for comparing a grid of
 * products, and a control you have to hover to discover does not exist on a
 * phone at all.
 */
const props = defineProps({
    product: { type: Object, required: true },
});

const { cart, add } = useCart();

// Only where the choice is not the customer's to make: one sellable variant.
// Anything with a size or a shade goes to its own page.
const quickAddId = computed(() => (props.product.in_stock ? props.product.default_variant_id ?? null : null));

const busy = computed(() => quickAddId.value !== null && cart.adding === quickAddId.value);
const done = computed(() => quickAddId.value !== null && cart.justAdded === quickAddId.value);

const discount = computed(() => {
    const now = Number(String(props.product.price ?? '').replace(/,/g, ''));
    const was = Number(String(props.product.was ?? '').replace(/,/g, ''));

    if (!now || !was || was <= now) {
        return null;
    }

    return Math.round(((was - now) / was) * 100);
});

function quickAdd() {
    if (!quickAddId.value) {
        return;
    }

    add({ productId: props.product.id, variantId: quickAddId.value, quantity: 1 });
}
</script>

<template>
    <div class="electro-card" :class="{ 'is-sold-out': !product.in_stock }">
        <div class="electro-card__pic">
            <Link :href="`/product/${product.slug}`" :aria-label="product.name">
                <div class="electro-card__img" :class="{ 'is-empty': !product.image }"
                    :style="product.image ? { backgroundImage: `url(${product.image})` } : null">
                    <span v-if="!product.image">No photo yet</span>
                </div>
            </Link>

            <span v-if="discount" class="electro-card__badge">−{{ discount }}%</span>
            <span v-if="!product.in_stock" class="electro-card__badge is-out">Out of stock</span>
        </div>

        <div class="electro-card__body">
            <h6><Link :href="`/product/${product.slug}`">{{ product.name }}</Link></h6>

            <div class="electro-card__price">
                <template v-if="product.price">
                    <b>{{ product.currency }} {{ product.price }}</b>
                    <s v-if="product.was">{{ product.currency }} {{ product.was }}</s>
                </template>
                <template v-else>Price on request</template>
            </div>

            <button v-if="quickAddId" type="button" class="electro-card__btn" :class="{ 'is-done': done }"
                :disabled="busy" @click="quickAdd">
                <template v-if="busy">Adding…</template>
                <template v-else-if="done">Added ✓</template>
                <template v-else>Add to basket</template>
            </button>

            <Link v-else :href="`/product/${product.slug}`" class="electro-card__btn electro-card__btn--ghost">
                {{ product.in_stock ? 'Choose options' : 'View product' }}
            </Link>
        </div>
    </div>
</template>
