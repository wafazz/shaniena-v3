<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';
import MoneyCell from '../../../Components/MoneyCell.vue';
import StatusPill from '../../../Components/StatusPill.vue';

const props = defineProps({
    queue: { type: Object, required: true },
    filters: { type: Object, required: true },
    orders: { type: Object, default: null },
    statuses: { type: Object, required: true },
});

const product = ref(props.filters.product ?? '');
const qty = ref(props.filters.qty ?? '');
const sort = ref(props.filters.sort ?? '');
const selected = ref([]);
const expanded = ref(null);

watch(() => props.queue.slug, () => { selected.value = []; expanded.value = null; });

const rows = computed(() => props.orders?.data ?? []);
const meta = computed(() => props.orders?.meta ?? null);
const hasFilter = computed(() => Boolean(props.filters.product) || props.filters.qty !== null);

const columns = [
    { key: 'select', label: '', headerClass: 'select-col' },
    { key: 'reference', label: 'Order' },
    { key: 'customer', label: 'Customer' },
    { key: 'lines', label: 'Product(s)' },
    { key: 'total', label: 'Order total (RM)', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'shipping', label: 'Shipping' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

// Stage moves an operator can make, labelled the way the source labelled them.
const TRANSITION_LABELS = {
    2: 'To Processing',
    3: 'In Delivery',
    4: 'Completed',
    5: 'RTS',
    6: 'Cancel',
};

const PRIMARY_TRANSITION = { 1: 2, 2: 3, 3: 4 };

function applyFilters() {
    router.get(window.location.pathname, {
        product: product.value || undefined,
        qty: qty.value || undefined,
        sort: sort.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    product.value = '';
    qty.value = '';
    sort.value = '';
    router.get(window.location.pathname, {}, { preserveState: true });
}

const selectableIds = computed(() => rows.value.filter((r) => r.transitions.length).map((r) => r.id));
const allSelected = computed(() => selectableIds.value.length > 0 && selected.value.length === selectableIds.value.length);

function toggleAll(event) {
    selected.value = event.target.checked ? [...selectableIds.value] : [];
}

function ship(order) {
    router.post(`/admin/orders/${order.id}/ship`, {}, { preserveScroll: true });
}

const page = usePage();

// A PDF, not an Inertia visit — open it rather than routing to it.
function printAwb(order) {
    window.open(`/admin/orders/${order.id}/awb`, '_blank', 'noopener');
}

function bulkPrint() {
    // POST so a long id list never ends up in a URL or a proxy log.
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/admin/orders/awb';
    form.target = '_blank';

    const field = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    field('_token', page.props.csrf_token);
    selected.value.forEach((id) => field('orders[]', id));

    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function bulkShip() {
    router.post('/admin/orders/ship', { orders: selected.value }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
    });
}

function move(order, to) {
    router.post(`/admin/orders/${order.id}/status`, { to }, { preserveScroll: true });
}

function bulkMove(to) {
    router.post('/admin/orders/status', { to, orders: selected.value }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
    });
}

// Every selected order is bookable.
const canBulkShip = computed(() => {
    const chosen = rows.value.filter((r) => selected.value.includes(r.id));
    return chosen.length > 0 && chosen.every((r) => r.can_ship);
});

// Every selected order already has an AWB to print.
const canBulkPrint = computed(() => {
    const chosen = rows.value.filter((r) => selected.value.includes(r.id));
    return chosen.length > 0 && chosen.every((r) => r.can_print);
});

// The one bulk move every selected order can actually take.
const bulkTarget = computed(() => {
    const chosen = rows.value.filter((r) => selected.value.includes(r.id));
    if (!chosen.length) return null;
    const shared = chosen.map((r) => PRIMARY_TRANSITION[r.status]).filter(Boolean);
    return shared.length === chosen.length && new Set(shared).size === 1 ? shared[0] : null;
});
</script>

<template>
    <Head :title="queue.title" />

    <AdminLayout :title="queue.title" :current="queue.slug" :breadcrumb="[{ label: 'Sales/Order' }]">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <form class="row g-2 align-items-end" @submit.prevent="applyFilters">
                    <div class="col-12 col-md-4">
                        <CFormLabel for="product" class="small mb-1">Product name</CFormLabel>
                        <CFormInput id="product" v-model="product" size="sm" placeholder="Matches any item in the order" />
                    </div>
                    <div class="col-6 col-md-2">
                        <CFormLabel for="qty" class="small mb-1">Total qty</CFormLabel>
                        <CFormInput id="qty" v-model="qty" type="number" min="1" size="sm" />
                    </div>
                    <div class="col-6 col-md-3">
                        <CFormLabel for="sort" class="small mb-1">Sort by quantity</CFormLabel>
                        <CFormSelect id="sort" v-model="sort" size="sm">
                            <option value="">Newest first</option>
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </CFormSelect>
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <CButton type="submit" color="primary" size="sm">Search</CButton>
                        <CButton v-if="hasFilter" color="secondary" variant="outline" size="sm" @click="resetFilters">Reset</CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <!-- Bulk bar appears only when a move is possible for everything picked. -->
        <div v-if="selected.length" class="d-flex flex-wrap align-items-center gap-3 mb-3 px-3 py-2 border rounded bg-body-tertiary">
            <span class="small fw-semibold"><span class="num">{{ selected.length }}</span> selected</span>
            <CButton v-if="canBulkShip" color="primary" size="sm" @click="bulkShip">Send to courier</CButton>
            <CButton v-if="canBulkPrint" color="secondary" variant="outline" size="sm" @click="bulkPrint">
                Print {{ selected.length }} AWB{{ selected.length === 1 ? '' : 's' }}
            </CButton>
            <CButton v-if="bulkTarget" :color="canBulkShip ? 'secondary' : 'primary'"
                :variant="canBulkShip ? 'outline' : undefined" size="sm" @click="bulkMove(bulkTarget)">
                Move to {{ statuses[bulkTarget] }}
            </CButton>
            <span v-else class="small text-body-secondary">Those orders are at different stages — no single move applies.</span>
            <CButton color="secondary" variant="ghost" size="sm" @click="selected = []">Clear</CButton>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <!-- Archives open empty: paging into 18,000 completed orders
                     helps nobody, so they ask for a filter first. -->
                <div v-if="!orders" class="empty-state">
                    <p class="fw-semibold mb-1">Search this archive</p>
                    <p class="small mb-0">
                        {{ queue.title }} holds the bulk of the order table. Enter a product name or quantity above to find what you need.
                    </p>
                </div>

                <DataTable
                    v-else
                    :columns="columns"
                    :rows="rows"
                    :meta="meta"
                    :empty-title="hasFilter ? 'No orders match that filter.' : 'Nothing waiting here.'"
                    :empty-body="hasFilter ? 'Try a shorter product name, or clear the filter.' : 'This queue is clear.'"
                >
                    <template #empty>
                        <CButton v-if="hasFilter" color="secondary" variant="outline" size="sm" @click="resetFilters">Clear filter</CButton>
                    </template>

                    <template #header:select>
                        <CFormCheck
                            :model-value="allSelected"
                            :disabled="!selectableIds.length"
                            aria-label="Select all orders on this page"
                            @change="toggleAll"
                        />
                    </template>

                    <template #cell:select="{ row }">
                        <CFormCheck
                            v-if="row.transitions.length"
                            :value="row.id"
                            :model-value="selected.includes(row.id)"
                            :aria-label="`Select order ${row.reference}`"
                            @change="selected.includes(row.id) ? selected = selected.filter((i) => i !== row.id) : selected.push(row.id)"
                        />
                    </template>

                    <template #cell:reference="{ row }">
                        <div class="code fw-semibold">{{ row.reference }}</div>
                        <div class="small text-body-secondary nowrap">{{ row.placed_at }}</div>
                    </template>

                    <template #cell:customer="{ row }">
                        <div>{{ row.customer }}</div>
                        <div class="small text-body-secondary">{{ row.country }}</div>
                    </template>

                    <template #cell:lines="{ row }">
                        <div v-if="!row.lines.length" class="small text-body-secondary">No items recorded</div>
                        <template v-else>
                            <div class="order-line">
                                {{ row.lines[0].name }}<span v-if="row.lines[0].variant" class="text-body-secondary"> — {{ row.lines[0].variant }}</span>
                                <span class="text-body-secondary ms-1">×{{ row.lines[0].quantity }}</span>
                            </div>
                            <CButton
                                v-if="row.lines.length > 1"
                                color="link"
                                size="sm"
                                class="p-0 small disclosure"
                                @click="expanded = expanded === row.id ? null : row.id"
                            >
                                {{ expanded === row.id ? 'Hide' : `+${row.lines.length - 1} more` }}
                            </CButton>
                            <div v-if="expanded === row.id" class="mt-1">
                                <div v-for="line in row.lines.slice(1)" :key="line.id" class="order-line small">
                                    {{ line.name }}<span v-if="line.variant" class="text-body-secondary"> — {{ line.variant }}</span>
                                    <span class="text-body-secondary ms-1">×{{ line.quantity }}</span>
                                </div>
                            </div>
                        </template>
                    </template>

                    <template #cell:total="{ row }">
                        <MoneyCell :amount="row.total" />
                    </template>

                    <template #cell:status="{ row }">
                        <StatusPill :status="row.status" />
                        <div v-if="row.payment_channel" class="small text-body-secondary mt-1">{{ row.payment_channel }}</div>
                    </template>

                    <template #cell:shipping="{ row }">
                        <div class="small">{{ row.courier_service ?? 'Not assigned' }}</div>
                        <a v-if="row.awb_number" :href="row.tracking_url" target="_blank" rel="noopener" class="small code">{{ row.awb_number }}</a>
                    </template>

                    <template #cell:actions="{ row }">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <CButton v-if="row.can_ship" size="sm" color="primary" @click="ship(row)">
                                Send to courier
                            </CButton>
                            <CButton v-if="row.can_print" size="sm" color="secondary" variant="outline"
                                @click="printAwb(row)">
                                {{ row.reprint ? 'Re-print AWB' : 'Print AWB' }}
                            </CButton>
                            <CButton
                                v-for="to in row.transitions"
                                :key="to"
                                size="sm"
                                :color="to === PRIMARY_TRANSITION[row.status] ? 'primary' : 'secondary'"
                                :variant="to === PRIMARY_TRANSITION[row.status] ? undefined : 'outline'"
                                @click="move(row, to)"
                            >{{ TRANSITION_LABELS[to] }}</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>

<style scoped>
.order-line {
    max-width: 34rem;
    overflow-wrap: anywhere;
}
</style>
