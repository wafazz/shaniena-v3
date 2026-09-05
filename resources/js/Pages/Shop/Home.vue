<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import ProductRow from '../../Storefront/ProductRow.vue';

// Ashion's service tiles. The source shipped these with typos baked in
// ("For all oder", "If good have Problems"); corrected here.
const SERVICES = [
    { icon: 'fa fa-car', title: 'Fast Shipping', body: 'On every order' },
    { icon: 'fa fa-money', title: 'Money Back Guarantee', body: 'If something is wrong' },
    { icon: 'fa fa-support', title: 'Online Support 24/7', body: 'Dedicated support' },
    { icon: 'fa fa-headphones', title: 'Secure Payment', body: '100% secure payment' },
];

defineProps({
    categories: { type: Array, default: () => [] },
    newArrivals: { type: Array, default: () => [] },
    bestSellers: { type: Array, default: () => [] },
    promos: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Home" />

    <StorefrontLayout current="home">
        <section class="categories spad">
            <div class="container">
                <div class="row">
                    <!-- Ashion drove this with Owl Carousel; a responsive grid
                         needs no jQuery and does not reflow on load. -->
                    <div v-for="category in categories" :key="category.id" class="col-lg-3 col-md-4 col-sm-6">
                        <div class="categories__item">
                            <h5 class="mb-1">
                                <Link :href="`/categories/${category.slug ?? category.id}`">{{ category.name }}</Link>
                            </h5>
                            <Link :href="`/categories/${category.slug ?? category.id}`" class="d-inline-block">Shop now</Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <ProductRow title="New Arrival" :products="newArrivals" empty="Nothing on sale in this country yet." />
        <ProductRow title="Top 8 Best Seller" :products="bestSellers" empty="No best-selling products found." />
        <ProductRow title="Promo" :products="promos" empty="No promo items found." />

        <section class="services spad">
            <div class="container">
                <div class="row">
                    <div v-for="service in SERVICES" :key="service.title" class="col-lg-3 col-md-6 col-sm-6">
                        <div class="services__item">
                            <i :class="service.icon"></i>
                            <h6>{{ service.title }}</h6>
                            <p>{{ service.body }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </StorefrontLayout>
</template>
