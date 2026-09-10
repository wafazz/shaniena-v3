<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { icon } from '../icons';
import FlashToast from '../Components/FlashToast.vue';

const props = defineProps({
    // The role_access slug of the current screen, used to mark the nav active.
    current: { type: String, default: '' },
    title: { type: String, required: true },
    // Trail above the title, e.g. [{ label: 'Sales/Order' }].
    breadcrumb: { type: Array, default: () => [] },
});

const page = usePage();
const sidebarVisible = ref(true);
const search = ref('');

const nav = computed(() => page.props.nav ?? []);
const admin = computed(() => page.props.auth?.admin ?? null);
const storeName = computed(() => page.props.store?.name ?? 'Shaniena');
const storeLogo = computed(() => page.props.store?.logo ?? null);
const canSearch = computed(() => Boolean(page.props.can?.searchOrders));

const isActive = (slug) => slug === props.current;
const groupIsOpen = (group) => group.items.some((item) => isActive(item.slug));

function href(slug) {
    return `/admin/${slug}`;
}

function submitSearch() {
    if (!search.value.trim()) {
        return;
    }

    router.get(href('search-order'), { search: search.value.trim() });
}

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div>
        <CSidebar
            class="border-end"
            colorScheme="dark"
            position="fixed"
            :visible="sidebarVisible"
            @visible-change="(value) => (sidebarVisible = value)"
        >
            <CSidebarHeader class="border-bottom">
                <CSidebarBrand class="fw-semibold">
                    <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="sidebar-brand-full">
                    <template v-else>{{ storeName }}</template>
                </CSidebarBrand>
            </CSidebarHeader>

            <CSidebarNav>
                <template v-for="(entry, index) in nav" :key="index">
                    <CNavTitle v-if="entry.type === 'title'">{{ entry.label }}</CNavTitle>

                    <CNavItem v-else-if="entry.type === 'item'">
                        <Link class="nav-link d-flex align-items-center" :class="{ active: isActive(entry.slug) }" :href="href(entry.slug)">
                            <CIcon v-if="entry.icon" customClassName="nav-icon" :icon="icon(entry.icon)" />
                            {{ entry.label }}
                            <span
                                v-if="entry.count !== undefined"
                                class="nav-count"
                                :class="{ 'is-waiting': entry.working && entry.count > 0 }"
                            >{{ entry.count }}</span>
                        </Link>
                    </CNavItem>

                    <CNavGroup v-else :visible="groupIsOpen(entry)">
                        <template #togglerContent>
                            <CIcon v-if="entry.icon" customClassName="nav-icon" :icon="icon(entry.icon)" />
                            {{ entry.label }}
                        </template>
                        <CNavItem v-for="child in entry.items" :key="child.slug">
                            <Link class="nav-link d-flex align-items-center" :class="{ active: isActive(child.slug) }" :href="href(child.slug)">
                                {{ child.label }}
                                <span
                                    v-if="child.count !== undefined"
                                    class="nav-count"
                                    :class="{ 'is-waiting': child.working && child.count > 0 }"
                                >{{ child.count }}</span>
                            </Link>
                        </CNavItem>
                    </CNavGroup>
                </template>

                <!-- An account with no grants is a real state: a new hire before
                     HQ has set their permissions. -->
                <div v-if="!nav.length" class="px-3 py-4 small text-body-secondary">
                    No sections assigned yet. Ask HQ to grant access.
                </div>
            </CSidebarNav>
        </CSidebar>

        <div class="wrapper d-flex flex-column min-vh-100">
            <CHeader position="sticky" class="mb-0 border-bottom">
                <CContainer fluid class="gap-3 align-items-center">
                    <CHeaderToggler class="d-lg-none" @click="sidebarVisible = !sidebarVisible" aria-label="Toggle navigation">
                        <CIcon :icon="icon('cilMenu')" size="lg" />
                    </CHeaderToggler>

                    <!-- Search lived inside the sidebar in the source, where it
                         scrolled away. At 20k+ orders it belongs here. -->
                    <form v-if="canSearch" class="flex-grow-1" style="max-width: 26rem" @submit.prevent="submitSearch">
                        <CInputGroup size="sm">
                            <CInputGroupText><CIcon :icon="icon('cilSearch')" /></CInputGroupText>
                            <CFormInput
                                v-model="search"
                                type="search"
                                placeholder="Order ID, name, phone or email"
                                aria-label="Search orders"
                            />
                        </CInputGroup>
                    </form>

                    <div class="ms-auto d-flex align-items-center gap-3">
                        <div v-if="admin" class="text-end lh-sm d-none d-sm-block">
                            <div class="small fw-semibold">{{ admin.name }}</div>
                            <div class="text-body-secondary" style="font-size: 0.72rem">{{ admin.role }}</div>
                        </div>
                        <CButton color="secondary" variant="outline" size="sm" @click="logout">
                            <CIcon :icon="icon('cilAccountLogout')" class="me-1" />Sign out
                        </CButton>
                    </div>
                </CContainer>
            </CHeader>

            <div class="body flex-grow-1 px-3 py-4">
                <CContainer fluid>
                    <CBreadcrumb v-if="breadcrumb.length" class="mb-2">
                        <CBreadcrumbItem v-for="crumb in breadcrumb" :key="crumb.label">{{ crumb.label }}</CBreadcrumbItem>
                        <CBreadcrumbItem active>{{ title }}</CBreadcrumbItem>
                    </CBreadcrumb>

                    <h1 class="h4 fw-bold mb-4">{{ title }}</h1>

                    <slot />
                </CContainer>
            </div>
        </div>

        <FlashToast />
    </div>
</template>
