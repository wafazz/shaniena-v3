<script setup>
/**
 * An empty state carries the action that fills it. "No data available" is the
 * copy this component exists to prevent.
 */
defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
});
</script>

<template>
    <div class="empty-state">
        <p class="fw-semibold mb-1">{{ title }}</p>
        <p v-if="body" class="small mb-3">{{ body }}</p>
        <slot />
    </div>
</template>
