<script setup>
import ProductCard from '../../ProductCard.vue';

/**
 * One product row in the Electro theme: a ruled heading with the title set
 * against it, then a tighter grid than Ashion's — four across on a desktop,
 * two on a phone, because this theme is for scanning a catalogue.
 */
defineProps({
    title: { type: String, required: true },
    products: { type: Array, default: () => [] },
    empty: { type: String, default: 'Nothing here yet.' },
});
</script>

<template>
    <section class="electro-row">
        <div class="container">
            <div class="electro-row__head">
                <h4>{{ title }}</h4>
            </div>

            <div v-if="products.length" class="row g-3">
                <div v-for="product in products" :key="product.id" class="col-lg-3 col-md-4 col-6">
                    <ProductCard :product="product" />
                </div>
            </div>

            <p v-else class="electro-row__empty">{{ empty }}</p>
        </div>
    </section>
</template>
