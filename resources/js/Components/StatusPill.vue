<script setup>
import { computed } from 'vue';

/**
 * The single order-status vocabulary. The source rendered status three
 * different ways with three different label sets; this is the one that wins,
 * taken from the queue screens because that is the wording operators use.
 *
 * Colour is never the only channel — every pill carries its label.
 */
const LABELS = {
    0: { label: 'To Pay', color: 'secondary' },
    1: { label: 'New Order', color: 'info' },
    2: { label: 'Processing', color: 'warning' },
    3: { label: 'In Delivery', color: 'primary' },
    4: { label: 'Completed', color: 'success' },
    5: { label: 'Returned', color: 'warning' },
    6: { label: 'Cancelled', color: 'danger' },
    10: { label: 'Failed Payment', color: 'danger' },
};

const props = defineProps({
    status: { type: [Number, String], required: true },
});

const meta = computed(() => LABELS[Number(props.status)] ?? { label: 'Unknown', color: 'secondary' });
</script>

<template>
    <CBadge :color="meta.color" shape="rounded-pill" class="text-uppercase">{{ meta.label }}</CBadge>
</template>
