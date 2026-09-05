<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const columns = [
    { key: 'ticket_no', label: 'Ticket' },
    { key: 'title', label: 'Subject' },
    { key: 'customer', label: 'Customer' },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

const PRIORITY = { urgent: 'danger', high: 'warning', medium: 'info', low: 'secondary' };
const STATUS = { new: 'info', in_progress: 'warning', waiting_customer: 'secondary', resolved: 'success', closed: 'secondary' };
const label = (value) => String(value ?? '').replace(/_/g, ' ');

function toggleClosed() {
    router.get('/admin/support/tickets', props.filters.closed ? {} : { closed: 1 }, { preserveState: true });
}
</script>

<template>
    <Head title="Support Tickets" />

    <AdminLayout title="Support Tickets" current="support/tickets" :breadcrumb="[{ label: 'Support' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="secondary" variant="outline" size="sm" @click="toggleClosed">
                {{ filters.closed ? 'Hide closed' : 'Include closed' }}
            </CButton>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="tickets.data" :meta="tickets.meta" min-width="48rem"
                    :empty-title="filters.closed ? 'No tickets at all yet.' : 'No open tickets.'"
                    empty-body="Urgent tickets sort to the top when they arrive.">
                    <template #cell:ticket_no="{ row }">
                        <span class="code">{{ row.ticket_no ?? '—' }}</span>
                        <div class="small text-body-secondary nowrap">{{ row.opened_at }}</div>
                    </template>
                    <template #cell:title="{ row }">
                        <div>{{ row.title ?? 'No subject' }}</div>
                        <div v-if="row.order_id" class="small text-body-secondary">Order {{ row.order_id }}</div>
                    </template>
                    <template #cell:customer="{ row }">
                        <div class="small">{{ row.customer }}</div>
                        <div class="small text-body-secondary">{{ row.email }}</div>
                    </template>
                    <template #cell:priority="{ row }">
                        <CBadge :color="PRIORITY[row.priority] ?? 'secondary'" shape="rounded-pill" class="text-uppercase">{{ row.priority }}</CBadge>
                    </template>
                    <template #cell:status="{ row }">
                        <CBadge :color="STATUS[row.status] ?? 'secondary'" shape="rounded-pill" class="text-uppercase">{{ label(row.status) }}</CBadge>
                    </template>
                    <template #cell:actions="{ row }">
                        <div class="d-flex justify-content-end">
                            <Link :href="`/admin/support/tickets/${row.id}`" class="btn btn-sm btn-outline-secondary">Open</Link>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
