<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '../../../Layouts/StorefrontLayout.vue';

const form = useForm({ email: '', password: '', remember: false });
</script>

<template>
    <Head title="Sign in" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5">
                        <h4 class="mb-4">Sign in</h4>

                        <form class="checkout__form" @submit.prevent="form.post('/login')">
                            <p v-if="form.errors.email" class="text-danger">{{ form.errors.email }}</p>

                            <div class="checkout__form__input">
                                <label for="form-email">Email<span>*</span></label>
                                <input id="form-email" v-model="form.email" type="email" autocomplete="username" required>
                            </div>
                            <div class="checkout__form__input">
                                <label for="form-password">Password<span>*</span></label>
                                <input id="form-password" v-model="form.password" type="password" autocomplete="current-password" required>
                            </div>

                            <label class="d-flex align-items-center gap-2 mb-3">
                                <input v-model="form.remember" type="checkbox"><span class="small">Keep me signed in</span>
                            </label>

                            <button type="submit" class="site-btn" :disabled="form.processing">
                                {{ form.processing ? 'Signing in…' : 'Sign in' }}
                            </button>
                        </form>

                        <p class="mt-4 mb-0">
                            No account yet? <Link href="/register">Create one</Link>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
