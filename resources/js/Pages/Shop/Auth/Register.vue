<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '../../../Layouts/StorefrontLayout.vue';

const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
</script>

<template>
    <Head title="Create an account" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5">
                        <h4 class="mb-4">Create an account</h4>

                        <form class="checkout__form" @submit.prevent="form.post('/register')">
                            <div class="checkout__form__input">
                                <label for="form-name">Name<span>*</span></label>
                                <input id="form-name" v-model="form.name" type="text" required>
                                <small v-if="form.errors.name" class="text-danger">{{ form.errors.name }}</small>
                            </div>
                            <div class="checkout__form__input">
                                <label for="form-email">Email<span>*</span></label>
                                <input id="form-email" v-model="form.email" type="email" autocomplete="username" required>
                                <small v-if="form.errors.email" class="text-danger">{{ form.errors.email }}</small>
                            </div>
                            <div class="checkout__form__input">
                                <label for="form-phone">Phone<span>*</span></label>
                                <input id="form-phone" v-model="form.phone" type="tel" required>
                            </div>
                            <div class="checkout__form__input">
                                <label for="form-password">Password<span>*</span></label>
                                <input id="form-password" v-model="form.password" type="password" autocomplete="new-password" required>
                                <small class="text-muted">At least 8 characters.</small>
                                <small v-if="form.errors.password" class="text-danger d-block">{{ form.errors.password }}</small>
                            </div>
                            <div class="checkout__form__input">
                                <label for="form-password-confirmation">Confirm password<span>*</span></label>
                                <input id="form-password-confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required>
                            </div>

                            <button type="submit" class="site-btn" :disabled="form.processing">
                                {{ form.processing ? 'Creating…' : 'Create account' }}
                            </button>
                        </form>

                        <p class="mt-4 mb-0">Already have one? <Link href="/login">Sign in</Link></p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
