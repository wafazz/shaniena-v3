<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit() {
    form.put('/admin/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Password" />

    <AdminLayout title="Password" current="password" :breadcrumb="[{ label: 'Account' }]">
        <CCard class="border" style="max-width: 32rem">
            <CCardHeader class="bg-transparent"><span class="fw-semibold">Change your password</span></CCardHeader>
            <CCardBody>
                <form class="row g-3" @submit.prevent="submit">
                    <div class="col-12">
                        <CFormLabel for="current_password">Current password</CFormLabel>
                        <CFormInput id="current_password" v-model="form.current_password" type="password"
                            autocomplete="current-password" :invalid="Boolean(form.errors.current_password)" />
                        <CFormFeedback v-if="form.errors.current_password" invalid>{{ form.errors.current_password }}</CFormFeedback>
                    </div>
                    <div class="col-12">
                        <CFormLabel for="password">New password</CFormLabel>
                        <CFormInput id="password" v-model="form.password" type="password"
                            autocomplete="new-password" :invalid="Boolean(form.errors.password)" />
                        <div class="form-text">At least 8 characters, upper and lower case, and a symbol.</div>
                        <CFormFeedback v-if="form.errors.password" invalid>{{ form.errors.password }}</CFormFeedback>
                    </div>
                    <div class="col-12">
                        <CFormLabel for="password_confirmation">Confirm new password</CFormLabel>
                        <CFormInput id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                    </div>
                    <div class="col-12">
                        <CButton type="submit" color="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Change password' }}
                        </CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
