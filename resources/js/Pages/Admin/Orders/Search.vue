<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';
import MoneyCell from '../../../Components/MoneyCell.vue';
import StatusPill from '../../../Components/StatusPill.vue';
import OrderDetailPanel from '../../../Components/OrderDetailPanel.vue';

const props = defineProps({
    term: { type: String, default: '' },
    results: { type: Object, default: null },
    queues: { type: Array, default: () => [] },
});

const term = ref(props.term);
const openOrderId = ref(null);

const rows = computed(() => props.results?.data ?? []);
const meta = computed(() => props.results?.meta ?? null);

const columns = [
    { key: 'reference', label: 'Order' },
    { key: 'customer', label: 'Customer' },
    { key: 'items', label: 'Items', align: 'right' },
    { key: 'total', label: 'Order total (RM)', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'shipping', label: 'Shipping' },
];

function submit() {
    router.get('/admin/search-order', { search: term.value.trim() || undefined }, { preserveState: true });
}

// Export
const showExport = ref(false);
const exportForm = ref({ queue: '', from: '', to: '', rows: 'orders', scoped: true });

const queueOptions = computed(() => [{ value: '', label: 'Every status' }, ...props.queues]);

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (exportForm.value.queue) params.set('queue', exportForm.value.queue);
    if (exportForm.value.from) params.set('from', exportForm.value.from);
    if (exportForm.value.to) params.set('to', exportForm.value.to);
    if (exportForm.value.rows !== 'orders') params.set('rows', exportForm.value.rows);
    // Carrying the search term keeps the file to what is on screen.
    if (exportForm.value.scoped && props.term) params.set('search', props.term);

    const query = params.toString();
    return `/admin/orders/export${query ? `?${query}` : ''}`;
});
</script>

<template>
    <Head title="Search Order" />

    <AdminLayout title="Search Order" current="search-order">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <form class="row g-2 align-items-end" @submit.prevent="submit">
                    <div class="col-12 col-md-7">
                        <CFormLabel for="search" class="small mb-1">Find an order</CFormLabel>
                        <CFormInput id="search" v-model="term" size="sm" autofocus placeholder="Order ID, name, phone or email" />
                    </div>
                    <div class="col-12 col-md-5 d-flex gap-2">
                        <CButton type="submit" color="primary" size="sm">Search</CButton>
                        <CButton type="button" color="secondary" variant="outline" size="sm"
                            @click="showExport = !showExport">
                            {{ showExport ? 'Hide export' : 'Export…' }}
                        </CButton>
                    </div>
                </form>

                <div v-if="showExport" class="border-top mt-3 pt-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-3">
                            <CFormLabel for="ex-queue" class="small mb-1">Status</CFormLabel>
                            <CFormSelect id="ex-queue" v-model="exportForm.queue" size="sm">
                                <option v-for="q in queueOptions" :key="q.value" :value="q.value">{{ q.label }}</option>
                            </CFormSelect>
                        </div>
                        <div class="col-6 col-md-2">
                            <CFormLabel for="ex-from" class="small mb-1">From</CFormLabel>
                            <CFormInput id="ex-from" v-model="exportForm.from" type="date" size="sm" />
                        </div>
                        <div class="col-6 col-md-2">
                            <CFormLabel for="ex-to" class="small mb-1">To</CFormLabel>
                            <CFormInput id="ex-to" v-model="exportForm.to" type="date" size="sm" />
                        </div>
                        <div class="col-12 col-md-3">
                            <CFormLabel for="ex-rows" class="small mb-1">One row per</CFormLabel>
                            <CFormSelect id="ex-rows" v-model="exportForm.rows" size="sm">
                                <option value="orders">Order</option>
                                <option value="items">Item</option>
                            </CFormSelect>
                        </div>
                        <div class="col-12 col-md-2">
                            <a :href="exportUrl" class="btn btn-primary btn-sm w-100">Download CSV</a>
                        </div>
                    </div>

                    <CFormCheck v-if="term" v-model="exportForm.scoped" class="small mt-2"
                        :label="`Limit to orders matching “${term}”`" />

                    <p class="small text-body-secondary mb-0 mt-2">
                        The file contains customers' names, addresses and phone numbers.
                        Every export is recorded in the activity log against your account.
                    </p>
                </div>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardBody class="p-0">
                <!-- Resting state: the source rendered an empty table here, which
                     says nothing about what the field will accept. -->
                <div v-if="!results" class="empty-state">
                    <p class="fw-semibold mb-1">Search across every order</p>
                    <p class="small mb-0">
                        Matches an order ID exactly, or a customer's name, phone or email. All statuses are searched, including To Pay and Failed Payment.
                    </p>
                </div>

                <DataTable
                    v-else
                    :columns="columns"
                    :rows="rows"
                    :meta="meta"
                    empty-title="No order matches that."
                    empty-body="Try the order ID on its own, or part of the customer's phone number."
                >
                    <template #cell:reference="{ row }">
                        <button type="button" class="reference-link code fw-semibold" @click="openOrderId = row.id">
                            {{ row.reference }}
                        </button>
                        <div class="small text-body-secondary nowrap">{{ row.placed_at }}</div>
                    </template>

                    <template #cell:customer="{ row }">
                        <div>{{ row.customer }}</div>
                        <div class="small text-body-secondary">{{ row.email }}</div>
                        <div class="small text-body-secondary code">{{ row.phone }}</div>
                    </template>

                    <template #cell:items="{ row }"><span class="num">{{ row.items }}</span></template>

                    <template #cell:total="{ row }"><MoneyCell :amount="row.total" /></template>

                    <template #cell:status="{ row }"><StatusPill :status="row.status" /></template>

                    <template #cell:shipping="{ row }">
                        <div class="small">{{ row.courier_service ?? 'Not assigned' }}</div>
                        <a v-if="row.awb_number" :href="row.tracking_url" target="_blank" rel="noopener" class="small code">{{ row.awb_number }}</a>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>
        <OrderDetailPanel :order-id="openOrderId" @close="openOrderId = null" />
    </AdminLayout>
</template>

<style scoped>
.reference-link {
    background: none;
    border: 0;
    padding: 0;
    color: var(--cui-body-color);
    text-align: left;
    text-decoration: none;
}

.reference-link:hover,
.reference-link:focus-visible {
    text-decoration: underline;
}
</style>
