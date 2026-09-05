<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '../../../Layouts/StorefrontLayout.vue';

const props = defineProps({ email: { type: String, default: '' } });

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const form = useForm({ email: props.email, code: '' });
const resend = useForm({ email: props.email });
</script>

<template>
    <Head title="Verify your email" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5">
                        <h4 class="mb-2">Verify your email</h4>
                        <p class="mb-4">Enter the 6-digit code we sent you.</p>

                        <p v-if="flash.success" class="text-success">{{ flash.success }}</p>
                        <p v-if="flash.error" class="text-danger">{{ flash.error }}</p>

                        <form @submit.prevent="form.post('/verify-email')">
                            <div class="checkout__input">
                                <p>Email<span>*</span></p>
                                <input v-model="form.email" type="email" required>
                            </div>
                            <div class="checkout__input">
                                <p>Code<span>*</span></p>
                                <input v-model="form.code" type="text" inputmode="numeric" maxlength="6"
                                    autocomplete="one-time-code" required>
                                <small v-if="form.errors.code" class="text-danger">{{ form.errors.code }}</small>
                            </div>

                            <button type="submit" class="site-btn" :disabled="form.processing">
                                {{ form.processing ? 'Checking…' : 'Verify' }}
                            </button>
                        </form>

                        <p class="mt-4 mb-0">
                            Didn't get it?
                            <button type="button" class="btn btn-link p-0 align-baseline"
                                :disabled="resend.processing"
                                @click="resend.email = form.email; resend.post('/verify-email/resend')">
                                Send another code
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
