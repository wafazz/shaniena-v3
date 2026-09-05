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
const message = computed(() => flash.value.error ?? flash.value.warning ?? flash.value.success ?? null);

// A warning is not a failure — it is a save the operator has to act on, so it
// stays until dismissed rather than sliding away on the success timer.
const tone = computed(() => {
    if (flash.value.error) return { color: 'danger', white: true, sticky: false };
    if (flash.value.warning) return { color: 'warning', white: false, sticky: true };
    return { color: 'success', white: true, sticky: false };
});

watch(message, (value) => { visible.value = Boolean(value); }, { immediate: true });
</script>

<template>
    <CToaster placement="top-end" class="p-3">
        <CToast
            v-if="visible"
            :visible="true"
            :color="tone.color"
            :autohide="!tone.sticky"
            :delay="7000"
            class="align-items-center"
            :class="tone.white ? 'text-white' : ''"
            @close="visible = false"
        >
            <div class="d-flex">
                <CToastBody>{{ message }}</CToastBody>
                <CToastClose class="me-2 m-auto" :white="tone.white" />
            </div>
        </CToast>
    </CToaster>
</template>
