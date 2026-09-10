<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

const props = defineProps({ countries: { type: Array, required: true } });

const form = useForm({ country_id: props.countries.length === 1 ? props.countries[0].id : '' });
</script>

<template>
    <Head title="Choose your country" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5">
                        <h4 class="mb-2">Where are we delivering?</h4>
                        <p class="mb-4">Prices, postage and payment options all depend on your country.</p>

                        <form class="checkout__form" @submit.prevent="form.post('/select-country')">
                            <div class="checkout__form__input">
                                <label for="form-country-id">Country<span>*</span></label>
                                <select id="form-country-id" v-model="form.country_id" class="form-select" required>
                                    <option value="">Select one</option>
                                    <option v-for="country in countries" :key="country.id" :value="country.id">
                                        {{ country.name }} ({{ country.sign }})
                                    </option>
                                </select>
                                <p v-if="form.errors.country_id" class="text-danger mt-2">{{ form.errors.country_id }}</p>
                            </div>

                            <button type="submit" class="site-btn mt-3" :disabled="form.processing || !form.country_id">
                                {{ form.processing ? 'Just a moment…' : 'Continue' }}
                            </button>
                        </form>

                        <p v-if="!countries.length" class="mt-4">
                            The shop is not open in any country yet. Please check back soon.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
