<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../../Layouts/AuthLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/admin/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Set a new password" />

    <AuthLayout title="Set a new password" subtitle="At least 8 characters, with upper and lower case and a symbol.">
        <CForm novalidate @submit.prevent="submit">
            <CAlert v-if="form.errors.email" color="danger" class="py-2 small">{{ form.errors.email }}</CAlert>

            <div class="mb-3">
                <CFormLabel for="email">Email address</CFormLabel>
                <CFormInput id="email" v-model="form.email" type="email" autocomplete="username" required />
            </div>

            <div class="mb-3">
                <CFormLabel for="password">New password</CFormLabel>
                <CFormInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    autofocus
                    :invalid="Boolean(form.errors.password)"
                />
                <CFormFeedback v-if="form.errors.password" invalid>{{ form.errors.password }}</CFormFeedback>
            </div>

            <div class="mb-4">
                <CFormLabel for="password_confirmation">Confirm new password</CFormLabel>
                <CFormInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
            </div>

            <CButton type="submit" color="primary" class="w-100" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save new password' }}
            </CButton>
        </CForm>
    </AuthLayout>
</template>
