<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

/**
 * Per-page SEO metadata.
 *
 * The source had none: the same <title> on every page, `lang="zxx"`, no
 * canonical, no Open Graph — and a meta description containing the visitor's
 * IP address.
 */
const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    image: { type: String, default: null },
    type: { type: String, default: 'website' },
    // Set false on pages that should never be indexed (cart, checkout, account).
    index: { type: Boolean, default: true },
});

const page = usePage();

// Absolute, supplied by the server so it is right under SSR too.
const canonical = computed(() => page.props.shop?.canonical ?? '');
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta v-if="description" name="description" :content="description">
        <meta v-if="!index" name="robots" content="noindex, follow">
        <link rel="canonical" :href="canonical">

        <meta property="og:type" :content="type">
        <meta property="og:title" :content="title">
        <meta v-if="description" property="og:description" :content="description">
        <meta v-if="image" property="og:image" :content="image">
        <meta property="og:url" :content="canonical">

        <meta name="twitter:card" :content="image ? 'summary_large_image' : 'summary'">
    </Head>
</template>
