<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AshionCard from './Themes/Ashion/ProductCard.vue';
import ElectroCard from './Themes/Electro/ProductCard.vue';

/**
 * One product tile, in whichever theme the shop is wearing.
 *
 * The pages import this and never a theme's own file, so adding a third theme
 * is a line here rather than an edit to every listing.
 */
defineProps({
    product: { type: Object, required: true },
});

const page = usePage();
const card = computed(() => (page.props.shop?.theme === 'electro' ? ElectroCard : AshionCard));
</script>

<template>
    <component :is="card" :product="product" />
</template>
