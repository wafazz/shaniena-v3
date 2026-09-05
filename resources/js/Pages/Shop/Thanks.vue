<script setup>
import { Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import Seo from '../../Storefront/Seo.vue';

defineProps({ order: { type: Object, required: true } });
</script>

<template>
    <Seo title="Order confirmed" description="Your order is confirmed." :index="false" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6 text-center">
                        <h3 class="mb-3">Thank you — your order is in</h3>

                        <p class="mb-4">
                            Order <b>{{ order.reference }}</b> for
                            <b>{{ order.currency }} {{ order.total }}</b>.
                            We've emailed the details to {{ order.email }}.
                        </p>

                        <p v-if="order.cod" class="mb-4">
                            You'll pay the courier when it arrives. Please have the exact amount ready if you can.
                        </p>

                        <div class="d-flex gap-2 justify-content-center">
                            <Link href="/track-order" class="site-btn">Track this order</Link>
                            <Link href="/" class="site-btn">Keep shopping</Link>
                        </div>

                        <p class="small text-muted mt-4 mb-0">
                            Keep the order number — you'll need it and your email address to check on it.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
