<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { CChartBar } from '@coreui/vue-chartjs';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';
import MoneyCell from '../../../Components/MoneyCell.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    daily: { type: Array, required: true },
    byCountry: { type: Array, required: true },
    byCourier: { type: Array, required: true },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const money = (v) => Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Filter state lives in the URL, so a filtered report is a shareable link.
function apply() {
    router.get('/admin/sales-report', { from: from.value, to: to.value }, { preserveState: true });
}

const chartData = {
    labels: props.daily.map((d) => d.label),
    datasets: [{
        label: 'Revenue (RM)',
        data: props.daily.map((d) => d.revenue),
        backgroundColor: '#cc2f30',
        // Without a cap, a range holding a single day renders one bar across
        // the whole chart, which reads as a broken block rather than a value.
        maxBarThickness: 48,
    }],
};

const chartOptions = {
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: (v) => money(v) } } },
};

const countryColumns = [
    { key: 'country', label: 'Country' },
    { key: 'orders', label: 'Orders', align: 'right' },
    { key: 'revenue', label: 'Revenue (RM)', align: 'right' },
];
const courierColumns = [
    { key: 'courier', label: 'Courier' },
    { key: 'orders', label: 'Orders', align: 'right' },
    { key: 'revenue', label: 'Revenue (RM)', align: 'right' },
];
</script>

<template>
    <Head title="Sales Report" />

    <AdminLayout title="Sales Report" current="sales-report">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <form class="row g-2 align-items-end" @submit.prevent="apply">
                    <div class="col-6 col-md-3">
                        <CFormLabel for="from" class="small mb-1">From</CFormLabel>
                        <CFormInput id="from" v-model="from" type="date" size="sm" />
                    </div>
                    <div class="col-6 col-md-3">
                        <CFormLabel for="to" class="small mb-1">To</CFormLabel>
                        <CFormInput id="to" v-model="to" type="date" size="sm" />
                    </div>
                    <div class="col-12 col-md-3">
                        <CButton type="submit" color="primary" size="sm">Apply</CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <div class="border rounded bg-body-tertiary px-3 py-2 mb-3 d-flex flex-wrap gap-4">
            <span class="small"><span class="text-body-secondary">Revenue</span> <b class="num ms-1">RM {{ money(summary.revenue) }}</b></span>
            <span class="small"><span class="text-body-secondary">Orders</span> <b class="num ms-1">{{ summary.orders.toLocaleString() }}</b></span>
            <span class="small"><span class="text-body-secondary">Average order</span> <b class="num ms-1">RM {{ money(summary.average) }}</b></span>
            <span class="small"><span class="text-body-secondary">Postage collected</span> <b class="num ms-1">RM {{ money(summary.postage) }}</b></span>
        </div>

        <CCard class="border mb-3">
            <CCardHeader class="bg-transparent"><span class="fw-semibold">Revenue by day</span></CCardHeader>
            <CCardBody>
                <div v-if="daily.length" class="chart-holder"><CChartBar :data="chartData" :options="chartOptions" /></div>
                <p v-else class="small text-body-secondary mb-0">No orders in this range.</p>
            </CCardBody>
        </CCard>

        <CRow class="g-3 align-items-start">
            <CCol :lg="6">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">By country</span></CCardHeader>
                    <CCardBody class="p-0">
                        <DataTable :columns="countryColumns" :rows="byCountry" min-width="0" empty-title="Nothing in this range.">
                            <template #cell:orders="{ row }"><span class="num">{{ row.orders }}</span></template>
                            <template #cell:revenue="{ row }"><MoneyCell :amount="row.revenue" /></template>
                        </DataTable>
                    </CCardBody>
                </CCard>
            </CCol>
            <CCol :lg="6">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">By courier</span></CCardHeader>
                    <CCardBody class="p-0">
                        <DataTable :columns="courierColumns" :rows="byCourier" min-width="0" empty-title="Nothing in this range.">
                            <template #cell:orders="{ row }"><span class="num">{{ row.orders }}</span></template>
                            <template #cell:revenue="{ row }"><MoneyCell :amount="row.revenue" /></template>
                        </DataTable>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>
    </AdminLayout>
</template>
