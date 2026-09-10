<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

const page = usePage();

const storeName = computed(() => page.props.store?.name ?? 'Shaniena');
const storeLogo = computed(() => page.props.store?.logo ?? null);
</script>

<template>
    <div class="auth-shell">
        <div class="auth-card">
            <!-- Whose console this is, before anyone types a password into it.
                 Text when no logo has been uploaded, so the screen is never
                 anonymous. -->
            <div class="auth-brand">
                <img v-if="storeLogo" :src="storeLogo" :alt="storeName">
                <span v-else>{{ storeName }}</span>
            </div>

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
