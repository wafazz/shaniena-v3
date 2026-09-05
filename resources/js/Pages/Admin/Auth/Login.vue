<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '../../../Layouts/AuthLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Sign in" subtitle="Shaniena admin console">
        <CForm novalidate @submit.prevent="submit">
            <CAlert v-if="form.errors.email" color="danger" class="py-2 small">{{ form.errors.email }}</CAlert>

            <div class="mb-3">
                <CFormLabel for="email">Email address</CFormLabel>
                <CFormInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    :invalid="Boolean(form.errors.email)"
                />
            </div>

            <div class="mb-3">
                <CFormLabel for="password">Password</CFormLabel>
                <CFormInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :invalid="Boolean(form.errors.password)"
                />
                <CFormFeedback v-if="form.errors.password" invalid>{{ form.errors.password }}</CFormFeedback>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-4">
                <CFormCheck id="remember" v-model="form.remember" label="Remember me" />
                <Link href="/admin/forgot-password" class="small">Forgot password?</Link>
            </div>

            <CButton type="submit" color="primary" class="w-100" :disabled="form.processing">
                {{ form.processing ? 'Signing in…' : 'Sign in' }}
            </CButton>
        </CForm>
    </AuthLayout>
</template>
