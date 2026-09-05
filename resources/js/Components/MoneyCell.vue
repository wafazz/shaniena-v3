<script setup>
import { computed } from 'vue';

/**
 * Numbers behave like numbers: right-aligned, tabular, two fixed decimals.
 * The currency symbol belongs in the column header, not on every cell — pass
 * `symbol` only where a row can differ from its neighbours.
 */
const props = defineProps({
    amount: { type: [Number, String], required: true },
    symbol: { type: String, default: '' },
});

const formatted = computed(() =>
    Number(props.amount).toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }),
);
</script>

<template>
    <span class="num">
        <span v-if="symbol" class="text-body-secondary me-1">{{ symbol }}</span>{{ formatted }}
    </span>
</template>
