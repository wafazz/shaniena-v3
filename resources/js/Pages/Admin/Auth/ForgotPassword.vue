<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '../../../Layouts/AuthLayout.vue';

const page = usePage();
const sent = computed(() => page.props.flash?.success ?? null);

const form = useForm({ email: '' });

function submit() {
    form.post('/admin/forgot-password');
}
</script>

<template>
    <Head title="Forgot password" />

    <AuthLayout title="Forgot password" subtitle="We'll email you a link to set a new one.">
        <CAlert v-if="sent" color="success" class="py-2 small">{{ sent }}</CAlert>

        <CForm novalidate @submit.prevent="submit">
            <CAlert v-if="form.errors.email" color="danger" class="py-2 small">{{ form.errors.email }}</CAlert>

            <div class="mb-4">
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

            <CButton type="submit" color="primary" class="w-100" :disabled="form.processing">
                {{ form.processing ? 'Sending…' : 'Email a reset link' }}
            </CButton>
        </CForm>

        <template #footer>
            <p class="text-center small mt-3 mb-0">
                <Link href="/admin/login">Back to sign in</Link>
            </p>
        </template>
    </AuthLayout>
</template>
