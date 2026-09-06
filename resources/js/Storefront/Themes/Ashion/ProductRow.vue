<script setup>
import ProductCard from '../../ProductCard.vue';

/**
 * One horizontal product row. The source repeated this block four times across
 * the homepage, each copy with slightly different bugs.
 */
defineProps({
    title: { type: String, required: true },
    products: { type: Array, default: () => [] },
    empty: { type: String, default: 'Nothing here yet.' },
});
</script>

<template>
    <section v-reveal class="product spad">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="section-title"><h4>{{ title }}</h4></div>
                </div>
            </div>

            <div v-if="products.length" class="row">
                <div v-for="product in products" :key="product.id" class="col-lg-3 col-md-4 col-sm-6">
                    <ProductCard :product="product" />
                </div>
            </div>

            <p v-else class="text-center mb-0">{{ empty }}</p>
        </div>
    </section>
</template>
