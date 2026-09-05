<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

defineProps({ posts: { type: Object, required: true } });
</script>

<template>
    <Head title="Blog" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>Blog</span></div>
            </div>
        </div>

        <section class="blog spad">
            <div class="container">
                <div v-if="posts.data.length" class="row">
                    <div v-for="post in posts.data" :key="post.id" class="col-lg-4 col-md-6">
                        <div class="blog__item">
                            <div class="blog__item__text">
                                <span>{{ post.published_at }}</span>
                                <h5><Link :href="`/blog/${post.id}`">{{ post.title }}</Link></h5>
                                <p>{{ post.excerpt }}</p>
                                <Link :href="`/blog/${post.id}`" class="blog__btn">Read more</Link>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center py-5 mb-0">Nothing published yet — check back soon.</p>
            </div>
        </section>
    </StorefrontLayout>
</template>
