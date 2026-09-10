<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MobileMenu from '../../MobileMenu.vue';
import { useCart } from '../../useCart';

/**
 * Electro — the catalogue theme.
 *
 * Built in the shape of the Electro storefront template: a utility bar, a
 * header whose middle is a search box with a category selector, the basket
 * sitting beside it with its total, and a category bar under that. Where
 * Ashion shows you one product at a time, this one is for a shopper who came
 * to compare — everything is one row closer.
 *
 * The markup and the palette are ours; what is borrowed is the arrangement.
 */
defineProps({
    current: { type: String, default: '' },
});

const page = usePage();
const shop = computed(() => page.props.shop ?? {});
const nav = computed(() => shop.value.nav ?? { brands: [], categories: [] });
const footer = computed(() => shop.value.footer ?? {});
const country = computed(() => shop.value.country ?? null);
const customer = computed(() => shop.value.customer ?? null);
const cartCount = computed(() => shop.value.cartCount ?? 0);

const { open: openCart } = useCart();

const menuOpen = ref(false);
const term = ref('');
const scope = ref('');
const bumped = ref(false);
let bumpTimer = null;

// The basket count is the one number in this header that changes without a
// page load, so it earns a beat of movement when it does.
watch(cartCount, (now, before) => {
    if (now === before) {
        return;
    }

    bumped.value = false;
    requestAnimationFrame(() => { bumped.value = true; });
    clearTimeout(bumpTimer);
    bumpTimer = setTimeout(() => { bumped.value = false; }, 500);
});

function search() {
    const q = term.value.trim();

    // A category with no words is still a search: it is the category page.
    if (!q && scope.value) {
        router.get(scope.value);

        return;
    }

    if (!q) {
        return;
    }

    router.get('/shop', { q });
}
</script>

<template>
    <div class="electro">
        <MobileMenu :open="menuOpen" @close="menuOpen = false" />

        <!-- Utility bar: how to reach the shop, and who you are. -->
        <div class="electro-topbar">
            <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="electro-topbar__contact">
                    <a v-if="footer.phone" :href="`tel:${footer.phone}`">
                        <i class="fa fa-phone" aria-hidden="true"></i> {{ footer.phone }}
                    </a>
                    <a v-if="footer.email" :href="`mailto:${footer.email}`">
                        <i class="fa fa-envelope" aria-hidden="true"></i> {{ footer.email }}
                    </a>
                </div>

                <div class="electro-topbar__links">
                    <Link href="/track-order">Track an order</Link>
                    <Link href="/change-country">{{ country ? country.name : 'Choose country' }}</Link>
                    <Link v-if="customer" href="/account">{{ customer.name || 'My account' }}</Link>
                    <Link v-else href="/login">Sign in</Link>
                </div>
            </div>
        </div>

        <header class="electro-header">
            <div class="container">
                <div class="electro-header__row">
                    <Link href="/" class="electro-logo">
                        <img v-if="footer.logo" :src="footer.logo" :alt="footer.name ?? 'Shaniena'">
                        <template v-else>{{ footer.name ?? 'Shaniena' }}</template>
                    </Link>

                    <!-- The search is the middle of this header, not an icon
                         that opens one. -->
                    <form class="electro-search" role="search" @submit.prevent="search">
                        <select v-model="scope" class="electro-search__scope" aria-label="Search in">
                            <option value="">All categories</option>
                            <option v-for="category in nav.categories" :key="category.id"
                                :value="`/categories/${category.slug ?? category.id}`">
                                {{ category.name }}
                            </option>
                        </select>

                        <input v-model="term" type="search" placeholder="Search for a product"
                            aria-label="Search for a product">

                        <button type="submit" aria-label="Search">
                            <span class="icon_search"></span>
                        </button>
                    </form>

                    <div class="electro-header__actions">
                        <button type="button" class="electro-basket"
                            :aria-label="`Basket, ${cartCount} item${cartCount === 1 ? '' : 's'}`"
                            @click="openCart">
                            <span class="icon_bag_alt" aria-hidden="true"></span>
                            <span class="electro-basket__text">
                                <small>Your basket</small>
                                <b>{{ cartCount }} item{{ cartCount === 1 ? '' : 's' }}</b>
                            </span>
                            <span v-if="cartCount" class="electro-basket__count" :class="{ 'is-bumped': bumped }">
                                {{ cartCount }}
                            </span>
                        </button>

                        <button type="button" class="electro-burger" aria-label="Open menu" @click="menuOpen = true">
                            <i class="fa fa-bars" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- The category bar. On a phone it scrolls sideways rather than
             disappearing: a nav that vanishes at a breakpoint is a removed
             feature. -->
        <nav class="electro-nav">
            <div class="container">
                <div class="electro-nav__scroll">
                    <Link href="/" :class="{ 'is-current': current === 'home' }">Home</Link>
                    <Link href="/shop" :class="{ 'is-current': current === 'search' }">All products</Link>
                    <Link v-for="category in nav.categories" :key="category.id"
                        :href="`/categories/${category.slug ?? category.id}`"
                        :class="{ 'is-current': current === 'category' }">{{ category.name }}</Link>
                    <Link href="/promo-item" :class="{ 'is-current': current === 'promos' }">Promos</Link>
                    <Link href="/blog">Blog</Link>
                    <Link href="/contact">Contact</Link>
                </div>
            </div>
        </nav>

        <slot />

        <footer class="electro-footer">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-4 col-md-6">
                        <h6>{{ footer.name ?? 'Shaniena' }}</h6>
                        <p v-if="footer.address">{{ footer.address }}</p>
                        <p v-if="footer.phone" class="mb-1">
                            <i class="fa fa-phone" aria-hidden="true"></i> {{ footer.phone }}
                        </p>
                        <p v-if="footer.email" class="mb-0">
                            <i class="fa fa-envelope" aria-hidden="true"></i> {{ footer.email }}
                        </p>

                        <div class="electro-footer__social">
                            <a v-if="footer.facebook" :href="footer.facebook" target="_blank" rel="noopener"
                                aria-label="Facebook"><i class="fa fa-facebook"></i></a>
                            <a v-if="footer.instagram" :href="footer.instagram" target="_blank" rel="noopener"
                                aria-label="Instagram"><i class="fa fa-instagram"></i></a>
                            <a v-if="footer.whatsapp" :href="`https://wa.me/${footer.whatsapp}`" target="_blank"
                                rel="noopener" aria-label="WhatsApp"><i class="fa fa-whatsapp"></i></a>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-3 col-6">
                        <h6>Shop</h6>
                        <ul>
                            <li><Link href="/shop">All products</Link></li>
                            <li><Link href="/promo-item">Promos</Link></li>
                            <li v-for="category in nav.categories.slice(0, 3)" :key="category.id">
                                <Link :href="`/categories/${category.slug ?? category.id}`">{{ category.name }}</Link>
                            </li>
                        </ul>
                    </div>

                    <div class="col-lg-3 col-md-3 col-6">
                        <h6>Your orders</h6>
                        <ul>
                            <li><Link href="/track-order">Track an order</Link></li>
                            <li><Link href="/checkout">Checkout</Link></li>
                            <li><Link href="/customer/support-ticket">Support tickets</Link></li>
                            <li><Link href="/account">My account</Link></li>
                        </ul>
                    </div>

                    <div class="col-lg-3 col-md-12">
                        <h6>About</h6>
                        <ul>
                            <li><Link href="/about">About us</Link></li>
                            <li><Link href="/blog">Blog</Link></li>
                            <li><Link href="/policy">Policy</Link></li>
                            <li><Link href="/terms">Terms &amp; conditions</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="electro-footer__legal">
                    &copy; {{ new Date().getFullYear() }} {{ footer.name ?? 'Shaniena' }}. All rights reserved.
                </div>
            </div>
        </footer>
    </div>
</template>
