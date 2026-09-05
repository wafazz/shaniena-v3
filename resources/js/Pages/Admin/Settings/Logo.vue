<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';

defineProps({ logos: { type: Array, required: true } });

const form = useForm({ image: null });
const fileInput = ref(null);

function upload() {
    form.post('/admin/logo-setting', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { form.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
}

function makeDefault(logo) {
    router.post(`/admin/logo-setting/${logo.id}/default`, {}, { preserveScroll: true });
}

function remove(logo) {
    if (!window.confirm('Remove this logo?')) return;
    router.delete(`/admin/logo-setting/${logo.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Logo Setting" />

    <AdminLayout title="Logo Setting" current="logo-setting" :breadcrumb="[{ label: 'Settings' }]">
        <CCard class="border mb-3">
            <CCardBody>
                <form class="row g-3 align-items-end" @submit.prevent="upload">
                    <div class="col-md-6">
                        <CFormLabel for="logo">Upload a logo</CFormLabel>
                        <CFormInput id="logo" ref="fileInput" type="file" accept="image/*"
                            :invalid="Boolean(form.errors.image)" @change="form.image = $event.target.files[0] ?? null" />
                        <CFormFeedback v-if="form.errors.image" invalid>{{ form.errors.image }}</CFormFeedback>
                    </div>
                    <div class="col-md-3">
                        <CButton type="submit" color="primary" :disabled="form.processing || !form.image">
                            {{ form.processing ? 'Uploading…' : 'Upload' }}
                        </CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardHeader class="bg-transparent"><span class="fw-semibold">Uploaded logos</span></CCardHeader>
            <CCardBody>
                <EmptyState v-if="!logos.length" title="No logo uploaded yet."
                    body="The storefront header falls back to the store name until one is set." />

                <div v-else class="d-flex flex-wrap gap-3">
                    <div v-for="logo in logos" :key="logo.id" class="border rounded p-3 text-center" style="width: 13rem">
                        <img :src="logo.url" alt="" style="max-height:56px;max-width:100%;object-fit:contain" class="mb-2">
                        <div class="small text-body-secondary mb-2">{{ logo.uploaded_at }}</div>
                        <CBadge v-if="logo.is_default" color="success" shape="rounded-pill" class="mb-2">In use</CBadge>
                        <div class="d-flex gap-1 justify-content-center">
                            <CButton v-if="!logo.is_default" size="sm" color="secondary" variant="outline" @click="makeDefault(logo)">Use this</CButton>
                            <CButton size="sm" color="secondary" variant="ghost" :disabled="logo.is_default"
                                :title="logo.is_default ? 'Pick another logo first' : ''" @click="remove(logo)">Remove</CButton>
                        </div>
                    </div>
                </div>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
