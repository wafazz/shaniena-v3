<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useCart } from './useCart';

/**
 * The phone navigation.
 *
 * Ashion drove its mobile menu with SlickNav, a jQuery plugin that was not
 * ported, so what was left was the raw markup: brands, categories, the country
 * switch and every page in one flat list of browser-default bullets, with no
 * way to tell a brand from a checkout link. Worse, the template hides
 * `.header__menu` and `.header__right` below 992px — so on a phone the search
 * box, the country and the basket did not exist at all. A control that
 * disappears at a breakpoint is a removed feature, not a responsive layout.
 *
 * So this panel carries the three things a shopper actually opens a menu for —
 * search, basket, where am I shipping to — and then the catalogue, grouped and
 * labelled, so brands read as brands and categories as categories.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const shop = computed(() => page.props.shop ?? {});
const nav = computed(() => shop.value.nav ?? { brands: [], categories: [] });
const country = computed(() => shop.value.country ?? null);
const customer = computed(() => shop.value.customer ?? null);
const cartCount = computed(() => shop.value.cartCount ?? 0);
const footer = computed(() => shop.value.footer ?? {});

const { open: openCart } = useCart();

const panel = ref(null);
const search = ref('');

const path = computed(() => {
    try {
        return new URL(page.url, 'http://x').pathname;
    } catch {
        return page.url;
    }
});

const isCurrent = (href) => path.value === href;

function go(href) {
    emit('close');
    router.get(href);
}

function submitSearch() {
    const term = search.value.trim();

    if (!term) {
        return;
    }

    emit('close');
    router.get('/shop', { q: term });
    search.value = '';
}

function showBasket() {
    emit('close');
    openCart();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(() => props.open, (isOpen) => {
    document.body.classList.toggle('menu-open', isOpen);

    if (isOpen) {
        document.addEventListener('keydown', onKeydown);
        requestAnimationFrame(() => panel.value?.focus());
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('menu-open');
});
</script>

<template>
    <div>
        <div class="shop-menu-overlay" :class="{ 'is-open': open }" @click="emit('close')"></div>

        <aside ref="panel" class="shop-menu" :class="{ 'is-open': open }" tabindex="-1"
            role="dialog" aria-modal="true" aria-label="Menu" :aria-hidden="!open">
            <div class="shop-menu__top">
                <span class="shop-menu__brand">{{ footer.name ?? 'Shaniena' }}</span>
                <button type="button" class="shop-menu__close" aria-label="Close menu"
                    @click="emit('close')">&times;</button>
            </div>

            <!-- First, because it is the only search on a phone: the header's
                 own is hidden below 992px by the template. -->
            <form class="shop-menu__search" role="search" @submit.prevent="submitSearch">
                <input v-model="search" type="search" placeholder="Search products"
                    aria-label="Search products">
                <button type="submit" aria-label="Search">
                    <span class="icon_search"></span>
                </button>
            </form>

            <div class="shop-menu__quick">
                <button type="button" class="shop-menu__quick-item" @click="showBasket">
                    <span class="icon_bag_alt" aria-hidden="true"></span>
                    <span>Basket</span>
                    <b v-if="cartCount">{{ cartCount }}</b>
                </button>

                <Link href="/change-country" class="shop-menu__quick-item" @click="emit('close')">
                    <span class="icon_pin_alt" aria-hidden="true"></span>
                    <span>{{ country ? country.name : 'Choose country' }}</span>
                </Link>
            </div>

            <nav class="shop-menu__nav">
                <section class="shop-menu__group">
                    <h3>Shop</h3>
                    <Link href="/" class="shop-menu__row" :class="{ 'is-current': isCurrent('/') }"
                        @click="emit('close')">Home</Link>
                    <Link href="/shop" class="shop-menu__row" :class="{ 'is-current': isCurrent('/shop') }"
                        @click="emit('close')">All products</Link>
                    <Link href="/promo-item" class="shop-menu__row" :class="{ 'is-current': isCurrent('/promo-item') }"
                        @click="emit('close')">Promos</Link>
                </section>

                <section v-if="nav.categories.length" class="shop-menu__group">
                    <h3>Categories</h3>
                    <Link v-for="category in nav.categories" :key="category.id"
                        :href="`/categories/${category.slug ?? category.id}`" class="shop-menu__row"
                        :class="{ 'is-current': isCurrent(`/categories/${category.slug ?? category.id}`) }"
                        @click="emit('close')">{{ category.name }}</Link>
                </section>

                <section v-if="nav.brands.length" class="shop-menu__group">
                    <h3>Brands</h3>
                    <Link v-for="brand in nav.brands" :key="brand.id"
                        :href="`/brands/${brand.slug ?? brand.id}`" class="shop-menu__row"
                        :class="{ 'is-current': isCurrent(`/brands/${brand.slug ?? brand.id}`) }"
                        @click="emit('close')">{{ brand.name }}</Link>
                </section>

                <section class="shop-menu__group">
                    <h3>Your orders</h3>
                    <Link v-if="customer" href="/account" class="shop-menu__row" @click="emit('close')">
                        {{ customer.name ? `${customer.name}'s account` : 'My account' }}
                    </Link>
                    <Link v-else href="/login" class="shop-menu__row" @click="emit('close')">Sign in</Link>
                    <Link href="/track-order" class="shop-menu__row" @click="emit('close')">Track an order</Link>
                    <Link href="/checkout" class="shop-menu__row" @click="emit('close')">Checkout</Link>
                    <Link href="/support" class="shop-menu__row" @click="emit('close')">Support tickets</Link>
                </section>

                <section class="shop-menu__group">
                    <h3>More</h3>
                    <Link href="/blog" class="shop-menu__row" @click="emit('close')">Blog</Link>
                    <Link href="/about" class="shop-menu__row" @click="emit('close')">About</Link>
                    <Link href="/contact" class="shop-menu__row" @click="emit('close')">Contact</Link>
                </section>
            </nav>

            <!-- A Malaysian shop is reached on WhatsApp more often than by
                 email, so it is a row and not a footnote. -->
            <div v-if="footer.whatsapp || footer.phone" class="shop-menu__foot">
                <a v-if="footer.whatsapp" :href="`https://wa.me/${footer.whatsapp}`" target="_blank" rel="noopener"
                    class="shop-menu__contact">
                    <i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp us
                </a>
                <a v-else :href="`tel:${footer.phone}`" class="shop-menu__contact">
                    <i class="fa fa-phone" aria-hidden="true"></i> {{ footer.phone }}
                </a>
            </div>
        </aside>
    </div>
</template>
