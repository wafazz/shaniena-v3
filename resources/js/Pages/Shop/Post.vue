<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

defineProps({ post: { type: Object, required: true } });
</script>

<template>
    <Head :title="post.title" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links">
                    <Link href="/">Home</Link><Link href="/blog">Blog</Link><span>{{ post.title }}</span>
                </div>
            </div>
        </div>

        <section class="blog-details spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <h4 class="mb-2">{{ post.title }}</h4>
                        <p class="text-muted mb-4">{{ post.published_at }} · {{ post.readers }} readers</p>
                        <div style="white-space: pre-wrap">{{ post.contents }}</div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
