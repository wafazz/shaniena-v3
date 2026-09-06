<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCart } from './useCart';

/**
 * One product tile, in Ashion's card markup.
 *
 * The template's hover rail held a single link that went to the product page,
 * which is the same thing the photo and the title already did. It now carries
 * the action the shopper actually came for: the tile adds to the basket where
 * that is unambiguous — one variant, in stock — and sends everyone else to the
 * page where the variant is chosen, rather than guessing on their behalf.
 */
const props = defineProps({
    product: { type: Object, required: true },
});

const { cart, add } = useCart();

// `default_variant_id` is only sent where the catalogue could resolve exactly
// one sellable variant; a product with a choice to make is not a one-click add.
const quickAddId = computed(() => (props.product.in_stock ? props.product.default_variant_id ?? null : null));

const busy = computed(() => cart.adding === quickAddId.value && quickAddId.value !== null);
const done = computed(() => cart.justAdded === quickAddId.value && quickAddId.value !== null);

function quickAdd() {
    if (!quickAddId.value) {
        return;
    }

    add({ productId: props.product.id, variantId: quickAddId.value, quantity: 1 });
}
</script>

<template>
    <div class="product__item" :class="{ 'is-sold-out': !product.in_stock }">
        <div class="product__item__pic" :class="{ 'is-empty': !product.image }">
            <!-- The photo sits on its own layer so it can scale inside the
                 frame on hover without moving the cards next to it. -->
            <div class="product__item__pic__img"
                :style="product.image ? { backgroundImage: `url(${product.image})` } : null"></div>

            <span v-if="!product.image" class="product__no-photo">No photo yet</span>
            <div v-if="!product.in_stock" class="product__out-of-stock">Out of stock</div>

            <ul v-if="product.in_stock" class="product__hover">
                <li>
                    <Link :href="`/product/${product.slug}`" :aria-label="`View ${product.name}`">
                        <span class="icon_search"></span>
                    </Link>
                </li>
                <li v-if="quickAddId">
                    <button type="button" :class="{ 'is-done': done }" :disabled="busy"
                        :aria-label="done ? `${product.name} added to basket` : `Add ${product.name} to basket`"
                        @click="quickAdd">
                        <span :class="done ? 'icon_check' : 'icon_bag_alt'"></span>
                    </button>
                </li>
            </ul>
        </div>

        <div class="product__item__text">
            <h6><Link :href="`/product/${product.slug}`">{{ product.name }}</Link></h6>
            <div class="product__price">
                <template v-if="product.price">
                    {{ product.currency }} {{ product.price }}
                    <span v-if="product.was">{{ product.currency }} {{ product.was }}</span>
                </template>
                <template v-else>Price on request</template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.product__item__pic.is-empty {
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product__no-photo {
    font-size: 12px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9a9a9a;
}

/* The source overlaid an out-of-stock PNG hosted on a different brand's
   domain. A label costs nothing and never 404s. */
.product__out-of-stock {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.75);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 13px;
    color: #111;
    z-index: 2;
}
</style>
