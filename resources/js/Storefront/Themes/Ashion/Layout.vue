<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MobileMenu from '../../MobileMenu.vue';
import { useCart } from '../../useCart';

/**
 * Ashion's header and footer, ported to Vue. One of the two storefront
 * themes; StorefrontLayout picks between them.
 *
 * The class names are the template's own, so its stylesheet applies unchanged.
 * What does not come across: SlickNav (the mobile menu is the offcanvas panel
 * below), and the floating cart widget the source layered on with inline
 * styles — the header cart already does that job.
 */
defineProps({
    // Marks the current top-level nav entry.
    current: { type: String, default: '' },
});

const page = usePage();
const shop = computed(() => page.props.shop ?? {});
const nav = computed(() => shop.value.nav ?? { brands: [], categories: [] });
const footer = computed(() => shop.value.footer ?? {});
const country = computed(() => shop.value.country ?? null);
const cartCount = computed(() => shop.value.cartCount ?? 0);

const offcanvasOpen = ref(false);
const searchOpen = ref(false);
const search = ref('');
const searchField = ref(null);

const { open: openCart } = useCart();

function submitSearch() {
    if (!search.value.trim()) {
        return;
    }

    searchOpen.value = false;
    router.get('/shop', { q: search.value.trim() });
}

// Opening the search panel should put the cursor in it. The template's own
// version relied on the shopper clicking the field they had just summoned.
watch(searchOpen, (open) => {
    if (open) {
        requestAnimationFrame(() => searchField.value?.focus());
    }
});

/*
 * Sticky header.
 *
 * It detaches once the page has scrolled past it, leaves on the way down and
 * comes back on the way up — so the basket and search are one gesture away
 * anywhere on a long category page, without holding a band of a phone screen
 * while somebody is reading a description.
 */
const header = ref(null);
const stuck = ref(false);
const hidden = ref(false);
let lastY = 0;
let ticking = false;

function onScroll() {
    if (ticking) {
        return;
    }

    ticking = true;

    requestAnimationFrame(() => {
        const y = window.scrollY;
        const height = header.value?.offsetHeight ?? 0;

        // Past its own height, so the header never sticks while still in view.
        const shouldStick = y > height;

        if (shouldStick !== stuck.value) {
            stuck.value = shouldStick;
            document.body.classList.toggle('has-stuck-header', shouldStick);
            // Measured, not guessed: the spacer that replaces the header in
            // the flow is exactly as tall as the header was.
            document.documentElement.style.setProperty('--header-h', `${height}px`);
        }

        // A few pixels of slack, or the header flickers on a trackpad.
        if (shouldStick && Math.abs(y - lastY) > 6) {
            hidden.value = y > lastY;
        }

        lastY = y;
        ticking = false;
    });
}

const bumped = ref(false);
let bumpTimer = null;

// The badge is the only feedback a shopper gets on pages where the drawer is
// already closed again, so it earns a beat of movement when it changes.
watch(cartCount, (now, before) => {
    if (now === before) {
        return;
    }

    bumped.value = false;
    requestAnimationFrame(() => { bumped.value = true; });
    clearTimeout(bumpTimer);
    bumpTimer = setTimeout(() => { bumped.value = false; }, 500);
});

onMounted(() => {
    lastY = window.scrollY;
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    clearTimeout(bumpTimer);
    document.body.classList.remove('has-stuck-header');
});
</script>

<template>
    <div>
        <MobileMenu :open="offcanvasOpen" @close="offcanvasOpen = false" />

        <header ref="header" class="header" :class="{ 'is-stuck': stuck, 'is-hidden': stuck && hidden }">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-xl-3 col-lg-2">
                        <div class="header__logo">
                            <Link href="/">{{ footer.name ?? 'Shaniena' }}</Link>
                        </div>
                    </div>

                    <div class="col-xl-6 col-lg-7">
                        <nav class="header__menu">
                            <ul>
                                <li :class="{ active: current === 'home' }"><Link href="/">Home</Link></li>

                                <li :class="{ active: current === 'brands' }">
                                    <a href="#" @click.prevent>Brands</a>
                                    <ul class="dropdown">
                                        <li v-for="brand in nav.brands" :key="brand.id">
                                            <Link :href="`/brands/${brand.slug ?? brand.id}`">{{ brand.name }}</Link>
                                        </li>
                                    </ul>
                                </li>

                                <li :class="{ active: current === 'categories' }">
                                    <a href="#" @click.prevent>Categories</a>
                                    <ul class="dropdown">
                                        <li v-for="category in nav.categories" :key="category.id">
                                            <Link :href="`/categories/${category.slug ?? category.id}`">{{ category.name }}</Link>
                                        </li>
                                    </ul>
                                </li>

                                <li :class="{ active: current === 'promos' }"><Link href="/promo-item">Promos</Link></li>
                                <li :class="{ active: current === 'checkout' }"><Link href="/checkout">Checkout</Link></li>

                                <li>
                                    <a href="#" @click.prevent>More...</a>
                                    <ul class="dropdown">
                                        <li><Link href="/track-order">Tracking</Link></li>
                                        <li><Link href="/contact">Contact</Link></li>
                                        <li><Link href="/support">Support Tickets</Link></li>
                                        <li><Link href="/blog">Blog</Link></li>
                                    </ul>
                                </li>
                            </ul>
                        </nav>
                    </div>

                    <div class="col-lg-3">
                        <div class="header__right">
                            <div class="header__right__auth">
                                <Link href="/change-country">{{ country ? country.name : 'Change Country' }}</Link>
                            </div>
                            <ul class="header__right__widget">
                                <li>
                                    <span class="icon_search search-switch" role="button" tabindex="0"
                                        aria-label="Search products" @click="searchOpen = !searchOpen"
                                        @keyup.enter="searchOpen = !searchOpen"></span>
                                </li>
                                <li>
                                    <!-- The basket opens beside the page now
                                         rather than replacing it with the
                                         checkout. -->
                                    <button type="button" class="cart-trigger"
                                        :aria-label="`Basket, ${cartCount} item${cartCount === 1 ? '' : 's'}`"
                                        @click="openCart">
                                        <span class="icon_bag_alt"></span>
                                        <div class="tip" :class="{ 'is-bumped': bumped }">{{ cartCount }}</div>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- The template hides .header__right below 992px, which took
                     the basket with it. This is the one control that comes back. -->
                <button type="button" class="header__cart-mobile"
                    :aria-label="`Basket, ${cartCount} item${cartCount === 1 ? '' : 's'}`"
                    @click="openCart">
                    <span class="icon_bag_alt" aria-hidden="true"></span>
                    <span v-if="cartCount" class="tip" :class="{ 'is-bumped': bumped }">{{ cartCount }}</span>
                </button>

                <div class="canvas__open" role="button" tabindex="0" aria-label="Open menu"
                    @click="offcanvasOpen = true" @keyup.enter="offcanvasOpen = true">
                    <i class="fa fa-bars"></i>
                </div>
            </div>
        </header>

        <!-- Holds the header's place in the flow while it is fixed, so the
             page does not jump by 84px the moment it sticks. -->
        <div class="header-spacer"></div>

        <div class="search-model" :style="{ display: searchOpen ? 'flex' : 'none' }">
            <div class="h-100 d-flex align-items-center justify-content-center">
                <div class="search-close-switch" role="button" tabindex="0" aria-label="Close search"
                    @click="searchOpen = false" @keyup.enter="searchOpen = false">+</div>
                <form class="search-model-form" @submit.prevent="submitSearch">
                    <input ref="searchField" v-model="search" type="text" placeholder="Search here....."
                        aria-label="Search products">
                </form>
            </div>
        </div>

        <slot />

        <footer class="footer">
            <div class="container">
                <div class="footer__top">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 col-sm-6">
                            <div class="footer__about">
                                <div class="footer__logo">
                                    <Link href="/">{{ footer.name ?? 'Shaniena' }}</Link>
                                </div>
                                <p v-if="footer.address">{{ footer.address }}</p>
                                <p v-if="footer.phone">{{ footer.phone }}</p>
                                <p v-if="footer.email">{{ footer.email }}</p>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <div class="footer__widget">
                                <h6>Quick links</h6>
                                <ul>
                                    <li><Link href="/about">About</Link></li>
                                    <li><Link href="/blog">Blogs &amp; Announcement</Link></li>
                                    <li><Link href="/contact">Contact</Link></li>
                                    <li><Link href="/support">Support Tickets</Link></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <div class="footer__widget">
                                <h6>Others</h6>
                                <ul>
                                    <li><Link href="/checkout">Checkout</Link></li>
                                    <li><Link href="/track-order">Orders Tracking</Link></li>
                                    <li><Link href="/policy">Policy</Link></li>
                                    <li><Link href="/terms">Terms &amp; Conditions</Link></li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12">
                            <div class="footer__widget">
                                <h6>Follow us</h6>
                                <div class="footer__social">
                                    <a v-if="footer.facebook" :href="footer.facebook" target="_blank" rel="noopener"><i class="fa fa-facebook"></i></a>
                                    <a v-if="footer.instagram" :href="footer.instagram" target="_blank" rel="noopener"><i class="fa fa-instagram"></i></a>
                                    <a v-if="footer.whatsapp" :href="`https://wa.me/${footer.whatsapp}`" target="_blank" rel="noopener"><i class="fa fa-whatsapp"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer__copyright">
                    <div class="footer__copyright__text">
                        <p>&copy; {{ new Date().getFullYear() }} {{ footer.name ?? 'Shaniena' }}. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </footer>

    </div>
</template>
