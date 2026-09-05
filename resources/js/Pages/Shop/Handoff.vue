<script setup>
import { onMounted, ref } from 'vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import Seo from '../../Storefront/Seo.vue';

/**
 * Auto-submitting handoff for gateways that require a POST.
 *
 * The form is rendered server-side with the gateway's own signed fields, so a
 * customer with JavaScript off can still submit it by hand.
 */
const props = defineProps({
    url: { type: String, required: true },
    fields: { type: Object, required: true },
    gateway: { type: String, required: true },
});

const form = ref(null);
onMounted(() => form.value?.submit());
</script>

<template>
    <Seo title="Taking you to payment" :index="false" />

    <StorefrontLayout>
        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6 text-center">
                        <h4 class="mb-3">Taking you to {{ gateway }}…</h4>
                        <p class="mb-4">Don't refresh or close this page.</p>

                        <form ref="form" :action="url" method="POST">
                            <input v-for="(value, name) in fields" :key="name" type="hidden" :name="name" :value="value">
                            <noscript>
                                <button type="submit" class="site-btn">Continue to payment</button>
                            </noscript>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
