<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

defineProps({ store: { type: Object, required: true } });
</script>

<template>
    <Head title="Contact" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>Contact</span></div>
            </div>
        </div>

        <section class="contact spad">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <h4 class="mb-3">Get in touch</h4>
                        <ul class="list-unstyled">
                            <li v-if="store.address" class="py-2"><b>Address</b><br>{{ store.address }}</li>
                            <li v-if="store.phone" class="py-2"><b>Phone</b><br>{{ store.phone }}</li>
                            <li v-if="store.email" class="py-2"><b>Email</b><br>
                                <a :href="`mailto:${store.email}`">{{ store.email }}</a></li>
                            <li v-if="store.whatsapp" class="py-2"><b>WhatsApp</b><br>
                                <a :href="`https://wa.me/${store.whatsapp}`" target="_blank" rel="noopener">{{ store.whatsapp }}</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <h4 class="mb-3">Need help with an order?</h4>
                        <p>Track it yourself, or open a support ticket and we'll pick it up.</p>
                        <div class="d-flex gap-2">
                            <Link href="/track-order" class="site-btn">Track an order</Link>
                            <Link href="/support" class="site-btn">Open a ticket</Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
