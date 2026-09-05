<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

/**
 * Ashion's header and footer, ported to Vue.
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

function submitSearch() {
    if (!search.value.trim()) {
        return;
    }

    searchOpen.value = false;
    router.get('/shop', { q: search.value.trim() });
}
</script>

<template>
    <div>
        <!-- Mobile menu. Ashion shipped this as SlickNav; it is plain state now. -->
        <div class="offcanvas-menu-overlay" :class="{ active: offcanvasOpen }" @click="offcanvasOpen = false"></div>
        <div class="offcanvas-menu-wrapper" :class="{ active: offcanvasOpen }">
            <div class="offcanvas__close" role="button" tabindex="0" aria-label="Close menu"
                @click="offcanvasOpen = false" @keyup.enter="offcanvasOpen = false">+</div>

            <ul class="offcanvas__widget">
                <li><span class="icon_bag_alt"></span> <Link href="/checkout">{{ cartCount }} items</Link></li>
            </ul>

            <div class="offcanvas__auth">
                <ul>
                    <li><Link href="/change-country">{{ country ? country.name : 'Change Country' }}</Link></li>
                </ul>
            </div>

            <nav class="slicknav_nav">
                <ul>
                    <li><Link href="/">Home</Link></li>
                    <li v-for="brand in nav.brands" :key="`m-b-${brand.id}`">
                        <Link :href="`/brands/${brand.slug ?? brand.id}`">{{ brand.name }}</Link>
                    </li>
                    <li v-for="category in nav.categories" :key="`m-c-${category.id}`">
                        <Link :href="`/categories/${category.slug ?? category.id}`">{{ category.name }}</Link>
                    </li>
                    <li><Link href="/promo-item">Promos</Link></li>
                    <li><Link href="/checkout">Checkout</Link></li>
                    <li><Link href="/track-order">Tracking</Link></li>
                    <li><Link href="/contact">Contact</Link></li>
                </ul>
            </nav>
        </div>

        <header class="header">
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
                                        <li><Link href="/customer/support-ticket">Support Tickets</Link></li>
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
                                    <Link href="/checkout" aria-label="Cart">
                                        <span class="icon_bag_alt"></span>
                                        <div class="tip">{{ cartCount }}</div>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="canvas__open" role="button" tabindex="0" aria-label="Open menu"
                    @click="offcanvasOpen = true" @keyup.enter="offcanvasOpen = true">
                    <i class="fa fa-bars"></i>
                </div>
            </div>
        </header>

        <div class="search-model" :style="{ display: searchOpen ? 'flex' : 'none' }">
            <div class="h-100 d-flex align-items-center justify-content-center">
                <div class="search-close-switch" role="button" tabindex="0" aria-label="Close search"
                    @click="searchOpen = false" @keyup.enter="searchOpen = false">+</div>
                <form class="search-model-form" @submit.prevent="submitSearch">
                    <input v-model="search" type="text" placeholder="Search here.....">
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
                                    <li><Link href="/customer/support-ticket">Support Tickets</Link></li>
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
