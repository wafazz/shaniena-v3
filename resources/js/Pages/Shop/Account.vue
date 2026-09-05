<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

const props = defineProps({
    member: { type: Object, required: true },
    orders: { type: Object, required: true },
});

const form = useForm({ ...props.member });
</script>

<template>
    <Seo title="Your account" description="Your orders and details." :index="false" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>Your account</span></div>
            </div>
        </div>

        <section class="checkout spad">
            <div class="container">
                <div class="row">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="mb-0">Your orders</h5>
                            <button type="button" class="btn btn-link p-0" @click="router.post('/logout')">Sign out</button>
                        </div>

                        <p v-if="!orders.data.length" class="text-muted">
                            No orders yet. <Link href="/">Have a look at what's new.</Link>
                        </p>

                        <div v-for="order in orders.data" :key="order.reference" class="border p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <b>{{ order.reference }}</b>
                                <span class="small text-muted">{{ order.placed_at }}</span>
                            </div>
                            <ul class="list-unstyled small mb-2">
                                <li v-for="(item, i) in order.items" :key="i">
                                    <Link v-if="item.slug" :href="`/product/${item.slug}`">{{ item.name }}</Link>
                                    <template v-else>{{ item.name }}</template>
                                    ×{{ item.quantity }}
                                </li>
                            </ul>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">{{ order.status }}</span>
                                <b>{{ order.currency }} {{ order.total }}</b>
                            </div>
                            <a v-if="order.tracking_url" :href="order.tracking_url" target="_blank" rel="noopener"
                                class="small d-inline-block mt-2">Track with {{ order.courier }} ({{ order.awb }})</a>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <h5 class="mb-3">Your details</h5>
                        <form @submit.prevent="form.put('/account', { preserveScroll: true })">
                            <div class="checkout__input">
                                <p>Name<span>*</span></p>
                                <input v-model="form.name" type="text" required>
                            </div>
                            <div class="checkout__input">
                                <p>Email</p>
                                <input :value="member.email" type="email" disabled>
                            </div>
                            <div class="checkout__input">
                                <p>Phone<span>*</span></p>
                                <input v-model="form.phone" type="tel" required>
                            </div>
                            <div class="checkout__input">
                                <p>Address</p>
                                <input v-model="form.address_1" type="text" placeholder="Street address">
                                <input v-model="form.address_2" type="text" class="mt-2" placeholder="Apartment, suite (optional)">
                            </div>
                            <div class="row">
                                <div class="col-4 checkout__input"><p>Postcode</p><input v-model="form.postcode" type="text"></div>
                                <div class="col-4 checkout__input"><p>City</p><input v-model="form.city" type="text"></div>
                                <div class="col-4 checkout__input"><p>State</p><input v-model="form.state" type="text"></div>
                            </div>

                            <button type="submit" class="site-btn" :disabled="form.processing">
                                {{ form.processing ? 'Saving…' : 'Save details' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
