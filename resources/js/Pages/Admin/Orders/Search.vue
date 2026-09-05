<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';
import MoneyCell from '../../../Components/MoneyCell.vue';
import StatusPill from '../../../Components/StatusPill.vue';

const props = defineProps({
    term: { type: String, default: '' },
    results: { type: Object, default: null },
});

const term = ref(props.term);

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
                    <div class="col-12 col-md-3">
                        <CButton type="submit" color="primary" size="sm">Search</CButton>
                    </div>
                </form>
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
                        <div class="code fw-semibold">{{ row.reference }}</div>
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
    </AdminLayout>
</template>
