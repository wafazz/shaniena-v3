<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Flash messages for every screen.
 *
 * The source gated its toast on a page-name whitelist that omitted four of
 * the six order queues, so success and error messages on those pages were
 * silently swallowed.
 */
const page = usePage();
const visible = ref(false);

const flash = computed(() => page.props.flash ?? {});
const message = computed(() => flash.value.success ?? flash.value.error ?? null);
const isError = computed(() => Boolean(flash.value.error));

watch(message, (value) => { visible.value = Boolean(value); }, { immediate: true });
</script>

<template>
    <CToaster placement="top-end" class="p-3">
        <CToast v-if="visible" :visible="true" :color="isError ? 'danger' : 'success'" class="text-white align-items-center" @close="visible = false">
            <div class="d-flex">
                <CToastBody>{{ message }}</CToastBody>
                <CToastClose class="me-2 m-auto" white />
            </div>
        </CToast>
    </CToaster>
</template>
