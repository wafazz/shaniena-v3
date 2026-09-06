<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AshionLayout from '../Storefront/Themes/Ashion/Layout.vue';
import ElectroLayout from '../Storefront/Themes/Electro/Layout.vue';
import CartDrawer from '../Storefront/CartDrawer.vue';
import VisitorCard from '../Storefront/VisitorCard.vue';
import InstallBanner from '../Storefront/InstallBanner.vue';

/**
 * The storefront shell, in whichever theme HQ has chosen.
 *
 * A theme owns the chrome — header, navigation, footer — and the catalogue
 * tiles. Everything layered over the page is shared, because the basket, the
 * install prompt and the visitor card behave the same whatever the shop looks
 * like, and two copies of a basket drawer is one copy that stops being tested.
 *
 * Both layouts are imported statically rather than resolved lazily: the shell
 * is server-rendered, and an async component would arrive after the crawler
 * has already read the page. The weight this costs is markup and a little
 * script — the stylesheets, which are the heavy part, are separate bundles and
 * only the chosen one is ever served.
 */
defineProps({
    current: { type: String, default: '' },
});

const page = usePage();

const layout = computed(() => (page.props.shop?.theme === 'electro' ? ElectroLayout : AshionLayout));
</script>

<template>
    <div>
        <component :is="layout" :current="current">
            <!-- Each Inertia visit remounts this, so the fade is per page and
                 costs no JavaScript. -->
            <div class="storefront-page">
                <slot />
            </div>
        </component>

        <CartDrawer />

        <VisitorCard />

        <InstallBanner />
    </div>
</template>
