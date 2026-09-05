<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';
import MoneyCell from '../../../Components/MoneyCell.vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    lowStockThreshold: { type: Number, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');

// One variant is selected per product row; switching rewrites the row's
// figures without a page load, as the source did with data- attributes.
const chosen = ref({});

const rows = computed(() => props.products.data);
const meta = computed(() => props.products.meta);

function variantOf(product) {
    const id = chosen.value[product.id];
    return product.variants.find((v) => v.id === id) ?? product.variants[0] ?? null;
}

const columns = [
    { key: 'id', label: 'Product ID', align: 'right' },
    { key: 'name', label: 'Product Name' },
    { key: 'sku', label: 'SKU' },
    { key: 'price', label: 'Retail / Sale (RM)', align: 'right' },
    { key: 'stock', label: 'Physical Stock Balance', align: 'right' },
    { key: 'sold', label: 'Total Sold', align: 'right' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function submitSearch() {
    router.get('/admin/stock-control', { search: search.value.trim() || undefined }, { preserveState: true });
}

// --- adjust modal --------------------------------------------------------
const adjusting = ref(null);
const form = useForm({ type: 'add', quantity: 1, comment: '' });

function openAdjust(product, variant) {
    adjusting.value = { product, variant };
    form.reset();
    form.clearErrors();
}

function submitAdjust() {
    form.post(`/admin/stock-control/${adjusting.value.variant.id}/adjust`, {
        preserveScroll: true,
        onSuccess: () => { adjusting.value = null; },
    });
}
</script>

<template>
    <Head title="Stock Control" />

    <AdminLayout title="Stock Control" current="stock-control" :breadcrumb="[{ label: 'Manage Product' }]">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <form class="row g-2 align-items-end" @submit.prevent="submitSearch">
                    <div class="col-12 col-md-6">
                        <CFormLabel for="search" class="small mb-1">Product name or SKU</CFormLabel>
                        <CFormInput id="search" v-model="search" size="sm" />
                    </div>
                    <div class="col-12 col-md-3">
                        <CButton type="submit" color="primary" size="sm">Search</CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable
                    :columns="columns"
                    :rows="rows"
                    :meta="meta"
                    :empty-title="filters.search ? 'No product matches that.' : 'No products yet.'"
                    :empty-body="filters.search ? 'Try part of the name, or the exact SKU.' : 'Add a product to start tracking stock.'"
                >
                    <template #cell:id="{ row }"><span class="code">{{ row.id }}</span></template>

                    <template #cell:name="{ row }">
                        <div>{{ row.name }}</div>
                        <CFormSelect
                            v-if="row.variants.length > 1"
                            size="sm"
                            class="mt-1"
                            style="max-width: 14rem"
                            :model-value="variantOf(row)?.id"
                            :aria-label="`Variant for ${row.name}`"
                            @change="chosen[row.id] = Number($event.target.value)"
                        >
                            <option v-for="variant in row.variants" :key="variant.id" :value="variant.id">
                                {{ variant.label }}
                            </option>
                        </CFormSelect>
                        <div v-else-if="variantOf(row)" class="small text-body-secondary">{{ variantOf(row).label }}</div>
                    </template>

                    <template #cell:sku="{ row }">
                        <span class="code nowrap">{{ variantOf(row)?.sku ?? '—' }}</span>
                    </template>

                    <!-- Q3: the source nested every selling country's market and
                         sale price in this one cell — ten money values at five
                         countries. Collapsed to the variant's own pair; per-country
                         pricing lives on the product form. -->
                    <template #cell:price="{ row }">
                        <MoneyCell :amount="variantOf(row)?.retail ?? 0" />
                        <div class="small text-body-secondary">
                            sale <MoneyCell :amount="variantOf(row)?.sale ?? 0" />
                        </div>
                    </template>

                    <template #cell:stock="{ row }">
                        <CBadge
                            :color="(variantOf(row)?.stock ?? 0) < lowStockThreshold ? 'danger' : 'success'"
                            shape="rounded-pill"
                        >{{ variantOf(row)?.stock ?? 0 }}</CBadge>
                        <div v-if="(variantOf(row)?.stock ?? 0) < lowStockThreshold" class="small text-danger mt-1">Low</div>
                    </template>

                    <template #cell:sold="{ row }">
                        <span class="num">{{ variantOf(row)?.sold ?? 0 }}</span>
                    </template>

                    <template #cell:actions="{ row }">
                        <div class="d-flex gap-1 justify-content-end">
                            <!-- Denied controls stay visible but disabled, with a
                                 reason — an operator who can see a control they
                                 lack knows to ask for it. -->
                            <CButton
                                size="sm"
                                color="primary"
                                :disabled="!can.adjustStock"
                                :title="can.adjustStock ? '' : 'You do not have permission to adjust stock.'"
                                @click="openAdjust(row, variantOf(row))"
                            >Add/Deduct</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>

        <CModal :visible="Boolean(adjusting)" @close="adjusting = null" alignment="center">
            <CModalHeader><CModalTitle>Add or deduct stock</CModalTitle></CModalHeader>
            <CModalBody v-if="adjusting">
                <p class="small text-body-secondary mb-3">
                    {{ adjusting.product.name }}
                    <span v-if="adjusting.variant"> — {{ adjusting.variant.label }}</span>
                    <span v-if="adjusting.variant?.sku" class="code ms-1">{{ adjusting.variant.sku }}</span>
                </p>

                <div class="mb-3">
                    <CFormLabel for="type">Movement</CFormLabel>
                    <CFormSelect id="type" v-model="form.type">
                        <option value="add">Add</option>
                        <option value="deduct">Deduct</option>
                    </CFormSelect>
                </div>

                <div class="mb-3">
                    <CFormLabel for="quantity">Quantity</CFormLabel>
                    <CFormInput id="quantity" v-model="form.quantity" type="number" min="1" step="1" :invalid="Boolean(form.errors.quantity)" />
                    <CFormFeedback v-if="form.errors.quantity" invalid>{{ form.errors.quantity }}</CFormFeedback>
                </div>

                <div>
                    <CFormLabel for="comment">Note <span class="text-body-secondary">(optional)</span></CFormLabel>
                    <CFormInput id="comment" v-model="form.comment" placeholder="Stock take, damaged, returned…" />
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="adjusting = null">Cancel</CButton>
                <CButton color="primary" :disabled="form.processing" @click="submitAdjust">
                    {{ form.processing ? 'Saving…' : 'Process' }}
                </CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
