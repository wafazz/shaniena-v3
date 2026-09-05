<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

const props = defineProps({
    searched: { type: Boolean, default: false },
    order: { type: Object, default: null },
});

const form = useForm({ order: '', email: '' });
</script>

<template>
    <Head title="Track your order" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>Track your order</span></div>
            </div>
        </div>

        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <h4 class="mb-2">Track your order</h4>
                        <p class="mb-4">Enter the order number from your confirmation email, and the email address you ordered with.</p>

                        <form @submit.prevent="form.get('/track-order', { preserveState: true })">
                            <div class="checkout__input">
                                <p>Order number<span>*</span></p>
                                <input v-model="form.order" type="text" placeholder="#00000123" required>
                            </div>
                            <div class="checkout__input">
                                <p>Email<span>*</span></p>
                                <input v-model="form.email" type="email" required>
                            </div>
                            <button type="submit" class="site-btn mt-2" :disabled="form.processing">
                                {{ form.processing ? 'Looking…' : 'Track' }}
                            </button>
                        </form>

                        <div v-if="searched && !order" class="mt-4">
                            <p class="text-danger mb-0">
                                We couldn't find an order with that number and email together. Check both and try again.
                            </p>
                        </div>

                        <div v-if="order" class="mt-4 p-3 border">
                            <h6 class="mb-3">Order {{ order.reference }}</h6>
                            <ul class="list-unstyled mb-3">
                                <li class="d-flex justify-content-between py-1"><span>Placed</span><b>{{ order.placed_at }}</b></li>
                                <li class="d-flex justify-content-between py-1"><span>Status</span><b>{{ order.status }}</b></li>
                                <li class="d-flex justify-content-between py-1"><span>Total</span>
                                    <b>{{ order.currency }} {{ order.total }}</b></li>
                                <li v-if="order.courier" class="d-flex justify-content-between py-1">
                                    <span>Courier</span><b>{{ order.courier }}</b></li>
                            </ul>
                            <ul class="list-unstyled mb-3">
                                <li v-for="(item, i) in order.items" :key="i" class="small">
                                    {{ item.name }} ×{{ item.quantity }}
                                </li>
                            </ul>
                            <a v-if="order.tracking_url" :href="order.tracking_url" target="_blank" rel="noopener" class="site-btn">
                                Track with {{ order.courier }} ({{ order.awb }})
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
