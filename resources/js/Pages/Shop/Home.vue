<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import ProductRow from '../../Storefront/ProductRow.vue';
import HeroSlider from '../../Storefront/HeroSlider.vue';

// Ashion's service tiles. The source shipped these with typos baked in
// ("For all oder", "If good have Problems"); corrected here.
const SERVICES = [
    { icon: 'fa fa-car', title: 'Fast Shipping', body: 'On every order' },
    { icon: 'fa fa-money', title: 'Money Back Guarantee', body: 'If something is wrong' },
    { icon: 'fa fa-support', title: 'Online Support 24/7', body: 'Dedicated support' },
    { icon: 'fa fa-headphones', title: 'Secure Payment', body: '100% secure payment' },
];

defineProps({
    slides: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    newArrivals: { type: Array, default: () => [] },
    bestSellers: { type: Array, default: () => [] },
    promos: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Home" />

    <StorefrontLayout current="home">
        <HeroSlider :slides="slides" />

        <section v-reveal class="categories spad">
            <div class="container">
                <div class="row">
                    <!-- Ashion drove this with Owl Carousel; a responsive grid
                         needs no jQuery and does not reflow on load.

                         .categories__item is display:flex, so the heading and
                         the link have to sit inside .categories__text — as
                         siblings they ran together as "Body CareShop now". -->
                    <div v-for="category in categories" :key="category.id" class="col-lg-3 col-md-4 col-sm-6">
                        <div
                            class="categories__item"
                            :class="{ 'categories__item--bare': !category.image }"
                            :style="category.image ? { backgroundImage: `url('${category.image}')` } : null"
                        >
                            <div class="categories__text">
                                <h4>{{ category.name }}</h4>
                                <p>{{ category.count }} item{{ category.count === 1 ? '' : 's' }}</p>
                                <Link :href="`/categories/${category.slug ?? category.id}`">Shop now</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <ProductRow title="New Arrival" :products="newArrivals" empty="Nothing on sale in this country yet." />
        <ProductRow title="Top 8 Best Seller" :products="bestSellers" empty="No best-selling products found." />
        <ProductRow title="Promo" :products="promos" empty="No promo items found." />

        <section v-reveal class="services spad">
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

<style scoped>
.categories__item {
    background-position: center;
    background-size: cover;
}

/* Until a category has a photo, the tile is a flat ground rather than 314px
   of white with text floating in it. */
.categories__item--bare {
    height: auto;
    min-height: 9rem;
    padding: 1.75rem;
    background: #f4f2ef;
}
</style>
