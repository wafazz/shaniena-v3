<script setup>
import { Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import Seo from '../../Storefront/Seo.vue';

defineProps({ order: { type: Object, required: true } });
</script>

<template>
    <Seo title="Payment not completed" :index="false" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6 text-center">
                        <h3 class="mb-3">That payment didn't go through</h3>

                        <p class="mb-4">
                            Order <b>{{ order.reference }}</b> hasn't been charged, and nothing has left your account.
                            Your basket is exactly as you left it.
                        </p>

                        <div class="d-flex gap-2 justify-content-center">
                            <Link href="/checkout" class="site-btn">Try again</Link>
                            <Link href="/support" class="site-btn">Get help</Link>
                        </div>

                        <p class="small text-muted mt-4 mb-0">
                            If your bank shows a charge, quote {{ order.reference }} when you contact us.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
