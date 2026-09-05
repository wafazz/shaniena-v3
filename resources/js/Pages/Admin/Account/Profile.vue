<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ profile: { type: Object, required: true } });

const form = useForm({
    f_name: props.profile.f_name,
    l_name: props.profile.l_name,
    email: props.profile.email,
    phone: props.profile.phone,
});
</script>

<template>
    <Head title="Profile" />

    <AdminLayout title="Profile" current="profile" :breadcrumb="[{ label: 'Account' }]">
        <CCard class="border" style="max-width: 38rem">
            <CCardHeader class="bg-transparent">
                <span class="fw-semibold">Your details</span>
                <span class="code small text-body-secondary ms-2">{{ profile.reference }}</span>
                <span class="small text-body-secondary ms-2">· {{ profile.role }}</span>
            </CCardHeader>
            <CCardBody>
                <form class="row g-3" @submit.prevent="form.put('/admin/profile', { preserveScroll: true })">
                    <div class="col-6">
                        <CFormLabel for="f_name">First name</CFormLabel>
                        <CFormInput id="f_name" v-model="form.f_name" :invalid="Boolean(form.errors.f_name)" />
                    </div>
                    <div class="col-6">
                        <CFormLabel for="l_name">Last name</CFormLabel>
                        <CFormInput id="l_name" v-model="form.l_name" :invalid="Boolean(form.errors.l_name)" />
                    </div>
                    <div class="col-12">
                        <CFormLabel for="email">Email address</CFormLabel>
                        <CFormInput id="email" v-model="form.email" type="email" :invalid="Boolean(form.errors.email)" />
                        <CFormFeedback v-if="form.errors.email" invalid>{{ form.errors.email }}</CFormFeedback>
                    </div>
                    <div class="col-12">
                        <CFormLabel for="phone">Phone</CFormLabel>
                        <CFormInput id="phone" v-model="form.phone" type="tel" :invalid="Boolean(form.errors.phone)" />
                    </div>
                    <div class="col-12">
                        <CButton type="submit" color="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save profile' }}
                        </CButton>
                        <span class="small text-body-secondary ms-3">Your permissions are set by HQ, not here.</span>
                    </div>
                </form>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
