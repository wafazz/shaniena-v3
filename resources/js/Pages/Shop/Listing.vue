<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import ProductCard from '../../Storefront/ProductCard.vue';

const props = defineProps({
    meta: { type: Object, required: true },
    products: { type: Array, required: true },
    paging: { type: Object, required: true },
});

const sort = ref(props.meta.sort);
const term = ref(props.meta.term ?? '');

const pages = computed(() => {
    const { current_page: current, last_page: last } = props.paging;
    const out = [];
    for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) out.push(i);
    return out;
});

// Sorting or paging used to blank the grid and rebuild it from the top of the
// page. It now dims in place, skeletons stand in for the cards, and the scroll
// position is kept — so the shopper's place in a long category survives a sort.
const busy = ref(false);

function go(params) {
    router.get(window.location.pathname, {
        ...Object.fromEntries(new URLSearchParams(window.location.search)),
        ...params,
    }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
    });
}

const emptyCopy = computed(() => {
    if (props.meta.kind === 'search') {
        return term.value
            ? 'Nothing matched that. Try a shorter word, or browse a category.'
            : 'Type what you are looking for above.';
    }
    return 'Nothing here just yet — new stock arrives regularly.';
});
</script>

<template>
    <Head :title="meta.title" />

    <StorefrontLayout :current="meta.kind">
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links">
                    <Link href="/">Home</Link>
                    <span>{{ meta.title }}</span>
                </div>
            </div>
        </div>

        <section class="product spad">
            <div class="container">
                <div class="row align-items-center mb-4">
                    <div class="col-md-7">
                        <div class="section-title mb-0"><h4>{{ meta.heading }}</h4></div>
                    </div>
                    <div class="col-md-5">
                        <form v-if="meta.kind === 'search'" class="d-flex gap-2" @submit.prevent="go({ q: term })">
                            <input v-model="term" type="search" class="form-control" placeholder="Search products">
                            <button type="submit" class="site-btn" :disabled="busy">
                                {{ busy ? 'Searching…' : 'Search' }}
                            </button>
                        </form>
                        <select v-else v-model="sort" class="form-select" aria-label="Sort products"
                            :disabled="busy" @change="go({ sort })">
                            <option value="newest">Newest first</option>
                            <option value="price-asc">Price: low to high</option>
                            <option value="price-desc">Price: high to low</option>
                            <option value="name">Name A–Z</option>
                        </select>
                    </div>
                </div>

                <div v-if="products.length" class="row" :class="{ 'grid--busy': busy }"
                    :aria-busy="busy">
                    <div v-for="product in products" :key="product.id" class="col-lg-3 col-md-4 col-sm-6">
                        <ProductCard :product="product" />
                    </div>
                </div>

                <!-- An empty grid mid-request is a loading state, not an empty
                     category: show the shape of what is coming instead of
                     telling the shopper there is nothing here. -->
                <div v-else-if="busy" class="row">
                    <div v-for="n in 4" :key="n" class="col-lg-3 col-md-4 col-sm-6">
                        <div class="product__item">
                            <div class="skeleton skeleton--pic"></div>
                            <div class="skeleton skeleton--line"></div>
                            <div class="skeleton skeleton--price"></div>
                        </div>
                    </div>
                </div>

                <p v-else class="text-center py-5 mb-0">{{ emptyCopy }}</p>

                <div v-if="paging.last_page > 1" class="d-flex justify-content-center gap-2 mt-4">
                    <button type="button" class="btn btn-sm btn-outline-dark" :disabled="paging.current_page === 1"
                        @click="go({ page: paging.current_page - 1 })">Previous</button>
                    <button v-for="p in pages" :key="p" type="button" class="btn btn-sm"
                        :class="p === paging.current_page ? 'btn-dark' : 'btn-outline-dark'"
                        @click="go({ page: p })">{{ p }}</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" :disabled="paging.current_page === paging.last_page"
                        @click="go({ page: paging.current_page + 1 })">Next</button>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
