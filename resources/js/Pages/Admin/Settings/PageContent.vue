<script setup>
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    page: { type: String, required: true },
    title: { type: String, required: true },
    slug: { type: String, required: true },
    description: { type: String, default: '' },
});

const form = useForm({ description: props.description ?? '' });
</script>

<template>
    <Head :title="title" />

    <AdminLayout :title="title" :current="slug" :breadcrumb="[{ label: 'Settings' }]">
        <CCard class="border">
            <CCardHeader class="bg-transparent">
                <span class="fw-semibold">Storefront copy</span>
                <span class="small text-body-secondary ms-2">Shown to customers on the {{ title }} page.</span>
            </CCardHeader>
            <CCardBody>
                <form @submit.prevent="form.put(`/admin/${slug}`, { preserveScroll: true })">
                    <CFormTextarea v-model="form.description" rows="18" :invalid="Boolean(form.errors.description)" />
                    <CFormFeedback v-if="form.errors.description" invalid class="d-block mb-2">{{ form.errors.description }}</CFormFeedback>
                    <CButton type="submit" color="primary" class="mt-3" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : `Save ${title}` }}
                    </CButton>
                </form>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
