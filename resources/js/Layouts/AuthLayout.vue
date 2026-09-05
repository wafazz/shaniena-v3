<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="auth-shell">
        <div class="auth-card">
            <h1 class="h4 fw-bold mb-1">{{ title }}</h1>
            <p v-if="subtitle" class="text-body-secondary small mb-4">{{ subtitle }}</p>
            <CCard class="border">
                <CCardBody class="p-4">
                    <slot />
                </CCardBody>
            </CCard>
            <slot name="footer" />
        </div>
    </div>
</template>
