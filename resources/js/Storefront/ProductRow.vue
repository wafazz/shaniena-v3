<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AshionRow from './Themes/Ashion/ProductRow.vue';
import ElectroRow from './Themes/Electro/ProductRow.vue';

/** One horizontal product row, in the shop's chosen theme. */
defineProps({
    title: { type: String, required: true },
    products: { type: Array, default: () => [] },
    empty: { type: String, default: 'Nothing here yet.' },
});

const page = usePage();
const row = computed(() => (page.props.shop?.theme === 'electro' ? ElectroRow : AshionRow));
</script>

<template>
    <component :is="row" :title="title" :products="products" :empty="empty" />
</template>
