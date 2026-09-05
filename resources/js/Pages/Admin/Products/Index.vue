<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

/**
 * The catalogue.
 *
 * The source had no product list: finding a product meant going through Stock
 * Control, which lists variants and cannot show a product that has none yet.
 */
const props = defineProps({
    products: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const brand = ref(props.filters.brand ?? '');
const status = ref(props.filters.status === null ? '' : String(props.filters.status));

const rows = computed(() => props.products?.data ?? []);
const meta = computed(() => props.products?.meta ?? null);

const columns = [
    { key: 'product', label: 'Product' },
    { key: 'placement', label: 'Category / Brand' },
    { key: 'variants', label: 'Variants', align: 'right' },
    { key: 'stock', label: 'Stock', align: 'right' },
    { key: 'price', label: 'Price (RM)', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', align: 'right' },
];

function apply() {
    router.get('/admin/product-list', {
        search: search.value.trim() || undefined,
        category: category.value || undefined,
        brand: brand.value || undefined,
        status: status.value === '' ? undefined : status.value,
    }, { preserveState: true, replace: true });
}

// The three selects apply themselves; only free text waits for a submit.
watch([category, brand, status], apply);

const money = (v) =>
    Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function priceLabel(row) {
    if (row.price_from === null) return '—';
    return row.price_from === row.price_to
        ? money(row.price_from)
        : `${money(row.price_from)} – ${money(row.price_to)}`;
}
</script>

<template>
    <Head title="All Products" />

    <AdminLayout title="All Products" current="product-list">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <form class="row g-2 align-items-end" @submit.prevent="apply">
                    <div class="col-12 col-md-4">
                        <CFormLabel for="search" class="small mb-1">Search</CFormLabel>
                        <CFormInput id="search" v-model="search" size="sm" placeholder="Name, slug or SKU" />
                    </div>
                    <div class="col-6 col-md-3">
                        <CFormLabel for="category" class="small mb-1">Category</CFormLabel>
                        <CFormSelect id="category" v-model="category" size="sm">
                            <option value="">All categories</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </CFormSelect>
                    </div>
                    <div class="col-6 col-md-2">
                        <CFormLabel for="brand" class="small mb-1">Brand</CFormLabel>
                        <CFormSelect id="brand" v-model="brand" size="sm">
                            <option value="">All brands</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </CFormSelect>
                    </div>
                    <div class="col-6 col-md-2">
                        <CFormLabel for="status" class="small mb-1">Status</CFormLabel>
                        <CFormSelect id="status" v-model="status" size="sm">
                            <option value="">Any</option>
                            <option value="1">Published</option>
                            <option value="0">Hidden</option>
                        </CFormSelect>
                    </div>
                    <div class="col-6 col-md-1 d-flex gap-2">
                        <CButton type="submit" color="secondary" variant="outline" size="sm" class="w-100">Go</CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="small text-body-secondary mb-0">
                <template v-if="meta">{{ meta.total }} product{{ meta.total === 1 ? '' : 's' }}</template>
            </p>
            <Link href="/admin/new-product" class="btn btn-primary btn-sm">Add product</Link>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable
                    :columns="columns"
                    :rows="rows"
                    :meta="meta"
                    empty-title="No product matches that."
                    empty-body="Clear the filters, or add the product if it is not in the catalogue yet."
                >
                    <template #cell:product="{ row }">
                        <div class="d-flex align-items-center gap-2">
                            <img v-if="row.image" :src="`/storage/${row.image}`" alt="" class="thumb">
                            <span v-else class="thumb thumb-empty" aria-hidden="true"></span>
                            <div>
                                <Link :href="`/admin/products/${row.id}/edit`" class="fw-semibold product-link">
                                    {{ row.name }}
                                </Link>
                                <div class="small text-body-secondary code d-none d-md-block">{{ row.slug }}</div>
                            </div>
                        </div>
                    </template>

                    <template #cell:placement="{ row }">
                        <div>{{ row.category ?? 'Uncategorised' }}</div>
                        <div class="small text-body-secondary">{{ row.brand ?? 'No brand' }}</div>
                    </template>

                    <template #cell:variants="{ row }">
                        <span class="num">{{ row.variants }}</span>
                    </template>

                    <template #cell:stock="{ row }">
                        <!-- Zero is the number that decides whether anyone can
                             buy it, so it is called out rather than left plain. -->
                        <span class="num" :class="row.stock > 0 ? '' : 'text-danger fw-semibold'">
                            {{ row.stock }}
                        </span>
                    </template>

                    <template #cell:price="{ row }">
                        <span class="num">{{ priceLabel(row) }}</span>
                    </template>

                    <template #cell:status="{ row }">
                        <CBadge :color="row.status ? 'success' : 'secondary'" shape="rounded-pill" class="text-uppercase">
                            {{ row.status ? 'Published' : 'Hidden' }}
                        </CBadge>
                    </template>

                    <template #cell:actions="{ row }">
                        <Link :href="`/admin/products/${row.id}/edit`" class="btn btn-sm btn-outline-secondary">
                            Edit
                        </Link>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>

<style scoped>
.thumb {
    /* Never let the flex row squash it out of square. */
    flex: 0 0 auto;
    width: 2.5rem;
    height: 2.5rem;
    object-fit: cover;
    border-radius: .25rem;
    border: 1px solid var(--cui-border-color);
}

.product-link {
    color: var(--cui-body-color);
    text-decoration: none;
}

.product-link:hover,
.product-link:focus-visible {
    text-decoration: underline;
}

.thumb-empty {
    display: inline-block;
    background: var(--cui-tertiary-bg);
}
</style>
