<script setup>
import { Link } from '@inertiajs/vue3';

/** One product tile, in Ashion's card markup. */
defineProps({
    product: { type: Object, required: true },
});
</script>

<template>
    <div class="product__item">
        <div class="product__item__pic set-bg" :class="{ 'is-empty': !product.image }"
            :style="product.image ? { backgroundImage: `url(${product.image})` } : {}">
            <span v-if="!product.image" class="product__no-photo">No photo yet</span>
            <div v-if="!product.in_stock" class="product__out-of-stock">Out of stock</div>
            <ul class="product__hover">
                <li>
                    <Link :href="`/product/${product.slug}`" :aria-label="`View ${product.name}`">
                        <span class="icon_bag_alt"></span>
                    </Link>
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
}
</style>
