<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
});
</script>

<template>
    <Head :title="title" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>{{ title }}</span></div>
            </div>
        </div>

        <section class="blog-details spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <h4 class="mb-4">{{ title }}</h4>
                        <div v-if="body" style="white-space: pre-wrap">{{ body }}</div>
                        <p v-else class="text-muted">This page has not been written yet.</p>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
