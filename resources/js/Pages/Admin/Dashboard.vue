<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { CChartLine } from '@coreui/vue-chartjs';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import MoneyCell from '../../Components/MoneyCell.vue';
import StatusPill from '../../Components/StatusPill.vue';

const props = defineProps({
    metrics: { type: Object, default: null },
    latestOrders: { type: Array, default: () => [] },
    activity: { type: Array, default: () => [] },
});

// Live figures overlay the page-load ones as they arrive.
const live = ref(null);

const m = computed(() => {
    if (!props.metrics) return null;
    if (!live.value) return props.metrics;

    return {
        ...props.metrics,
        generated_at: live.value.generated_at,
        today: live.value.today,
        queues: live.value.queues,
        visitors: live.value.visitors,
    };
});

const liveOrders = computed(() => live.value?.orders ?? props.latestOrders);

// Polled, not streamed. The source held a server-sent-event connection open
// per signed-in admin and re-ran eight queries every two seconds.
const POLL_MS = 30000;
let timer = null;

async function pull() {
    try {
        const response = await fetch('/admin/dashboard/live', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (response.ok) live.value = await response.json();
    } catch {
        // A missed poll is not worth surfacing; the next one will catch up.
    }
}

onMounted(() => {
    pull();
    timer = setInterval(() => {
        // Nothing to refresh while the tab is in the background.
        if (document.visibilityState === 'visible') pull();
    }, POLL_MS);
});

onBeforeUnmount(() => clearInterval(timer));

const money = (v) =>
    Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Month-on-month, only shown when last month actually has a figure to compare
// against — a delta against zero is noise, not information.
const monthDelta = computed(() => {
    const now = m.value?.this_month?.sales ?? 0;
    const prev = m.value?.last_month?.sales ?? 0;
    if (!prev) return null;
    return ((now - prev) / prev) * 100;
});

const generatedAt = computed(() => {
    if (!m.value?.generated_at) return null;
    return new Date(m.value.generated_at).toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' });
});

// Three working queues, each a link into the list that clears it.
const QUEUES = [
    { slug: 'new-order', label: 'New Order', status: 1 },
    { slug: 'process-order', label: 'Process Order', status: 2 },
    { slug: 'indelivery-order', label: 'Indelivery Order', status: 3 },
];

const chartData = computed(() => ({
    labels: (m.value?.trend ?? []).map((d) => d.label),
    datasets: [{
        label: 'Sales (RM)',
        data: (m.value?.trend ?? []).map((d) => d.sales),
        borderColor: '#cc2f30',
        backgroundColor: 'rgba(204, 47, 48, 0.08)',
        fill: true,
        tension: 0.3,
        pointRadius: 2,
    }],
}));

const chartOptions = {
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { callback: (v) => money(v) } },
    },
};

const orderColumns = [
    { key: 'reference', label: 'Order' },
    { key: 'customer', label: 'Customer' },
    { key: 'summary', label: 'Total (RM)', align: 'right' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout title="Dashboard" current="dashboard">
        <!-- Loading shows a dash, never 0. The source rendered a hard 0 that
             jumped two seconds later, which reads as a dead store. -->
        <div class="d-flex align-items-baseline justify-content-between flex-wrap gap-2 mb-3">
            <p class="small text-body-secondary mb-0">
                <template v-if="generatedAt">Figures as at {{ generatedAt }}</template>
                <template v-else>Loading figures…</template>
            </p>

            <!-- Browsing right now. Its own line rather than a headline tile:
                 it is a pulse, not a number anyone acts on. -->
            <p v-if="m && m.visitors" class="small mb-0 d-flex align-items-center gap-2">
                <span class="live-dot" :class="{ 'is-quiet': !m.visitors.live }" aria-hidden="true"></span>
                <span>
                    <span class="fw-semibold num">{{ m.visitors.live }}</span>
                    <span class="text-body-secondary"> browsing now · {{ m.visitors.today }} today</span>
                </span>
            </p>
        </div>

        <!-- Three headline figures, not a row of eight. Total Products and
             all-time order count are trivia; they sit in the strip below. -->
        <CRow class="g-3 mb-3">
            <CCol :md="4">
                <CCard class="border h-100">
                    <CCardBody>
                        <p class="text-uppercase small text-body-secondary mb-1" style="letter-spacing:.06em">Sales this month</p>
                        <p class="h3 fw-bold num mb-1">
                            <span class="text-body-secondary fs-6 me-1">RM</span>
                            <template v-if="m">{{ money(m.this_month.sales) }}</template><template v-else>—</template>
                        </p>
                        <p v-if="monthDelta !== null" class="small mb-0" :class="monthDelta >= 0 ? 'text-success' : 'text-danger'">
                            {{ monthDelta >= 0 ? '+' : '' }}{{ monthDelta.toFixed(1) }}% vs last month (RM {{ money(m.last_month.sales) }})
                        </p>
                        <p v-else class="small text-body-secondary mb-0">No sales last month to compare against</p>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :md="4">
                <CCard class="border h-100">
                    <CCardBody>
                        <p class="text-uppercase small text-body-secondary mb-1" style="letter-spacing:.06em">Today</p>
                        <p class="h3 fw-bold num mb-1">
                            <span class="text-body-secondary fs-6 me-1">RM</span>
                            <template v-if="m">{{ money(m.today.sales) }}</template><template v-else>—</template>
                        </p>
                        <p class="small text-body-secondary mb-0">
                            <template v-if="m && m.today.orders">{{ m.today.orders }} order{{ m.today.orders === 1 ? '' : 's' }} so far</template>
                            <template v-else-if="m">No orders yet today</template>
                            <template v-else>—</template>
                        </p>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :md="4">
                <CCard class="border h-100">
                    <CCardBody>
                        <p class="text-uppercase small text-body-secondary mb-1" style="letter-spacing:.06em">Waiting on you</p>
                        <div class="d-flex flex-column gap-1">
                            <Link
                                v-for="queue in QUEUES"
                                :key="queue.slug"
                                :href="`/admin/${queue.slug}`"
                                class="d-flex align-items-baseline justify-content-between text-body text-decoration-none py-1 border-bottom"
                            >
                                <span class="small">{{ queue.label }}</span>
                                <span
                                    class="fw-semibold num"
                                    :class="m && (m.queues[queue.status] ?? 0) > 0 ? 'text-danger' : 'text-body-secondary'"
                                >
                                    <template v-if="m">{{ m.queues[queue.status] ?? 0 }}</template><template v-else>—</template>
                                </span>
                            </Link>
                        </div>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>

        <!-- Secondary strip: hairlines, not four more cards. -->
        <div class="border rounded bg-body-tertiary px-3 py-2 mb-3 d-flex flex-wrap gap-4">
            <span class="small"><span class="text-body-secondary">All-time sales</span>
                <b class="num ms-1">RM {{ m ? money(m.all_time.sales) : '—' }}</b></span>
            <span class="small"><span class="text-body-secondary">Orders</span>
                <b class="num ms-1">{{ m ? m.all_time.orders.toLocaleString() : '—' }}</b></span>
            <span class="small"><span class="text-body-secondary">Returned</span>
                <b class="num ms-1">{{ m ? m.all_time.returned.toLocaleString() : '—' }}</b></span>
            <span class="small"><span class="text-body-secondary">Products</span>
                <b class="num ms-1">{{ m ? m.all_time.products.toLocaleString() : '—' }}</b></span>
        </div>

        <CRow class="g-3 align-items-start">
            <CCol :lg="7">
                <CCard class="border">
                    <CCardHeader class="bg-transparent">
                        <span class="fw-semibold">Sales, last 14 days</span>
                    </CCardHeader>
                    <CCardBody>
                        <!-- CChartLine wraps the canvas in a div of its own, so a
                             height on this element alone never reaches the canvas. -->
                        <div v-if="m" class="chart-holder">
                            <CChartLine :data="chartData" :options="chartOptions" />
                        </div>
                        <div v-else class="skeleton" style="height: 260px"></div>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :lg="5">
                <CCard class="border">
                    <CCardHeader class="bg-transparent d-flex align-items-center justify-content-between">
                        <span class="fw-semibold">Latest orders</span>
                        <Link href="/admin/new-order" class="small">View queue</Link>
                    </CCardHeader>
                    <CCardBody class="p-0">
                        <DataTable
                            :columns="orderColumns"
                            :rows="liveOrders"
                            min-width="0"
                            empty-title="No orders yet."
                            empty-body="They'll appear here as they come in."
                        >
                            <template #cell:reference="{ row }">
                                <div class="code small fw-semibold">{{ row.reference }}</div>
                                <div class="text-body-secondary nowrap" style="font-size:.75rem">{{ row.placed_at }}</div>
                            </template>
                            <template #cell:customer="{ row }">
                                <div class="small">{{ row.customer }}</div>
                                <div class="text-body-secondary" style="font-size:.75rem">{{ row.country }}</div>
                            </template>
                            <template #cell:summary="{ row }">
                                <MoneyCell :amount="row.total" />
                                <div class="mt-1"><StatusPill :status="row.status" /></div>
                            </template>
                        </DataTable>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>

        <CCard class="border mt-3">
            <CCardHeader class="bg-transparent"><span class="fw-semibold">Staff activity</span></CCardHeader>
            <CCardBody>
                <div v-if="!activity.length" class="empty-state py-3">
                    <p class="small mb-0">No staff activity recorded yet.</p>
                </div>
                <ul v-else class="list-unstyled mb-0">
                    <li v-for="row in activity" :key="row.id" class="d-flex justify-content-between gap-3 py-2 border-bottom">
                        <span class="small">{{ row.description }}</span>
                        <span class="small text-body-secondary nowrap">{{ row.actor }} · {{ row.at }}</span>
                    </li>
                </ul>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>

<style scoped>
.live-dot {
    width: .5rem;
    height: .5rem;
    border-radius: 50%;
    background: var(--cui-success, #2eb85c);
    display: inline-block;
}

.live-dot.is-quiet {
    background: var(--cui-secondary-color, #8a93a2);
}
</style>
