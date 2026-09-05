<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

const props = defineProps({
    entries: { type: Object, required: true },
    staff: { type: Array, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const user = ref(props.filters.user ?? '');
const type = ref(props.filters.type ?? '');

const columns = [
    { key: 'description', label: 'What happened' },
    { key: 'actor', label: 'Who' },
    { key: 'type', label: 'Area' },
    { key: 'at', label: 'When' },
];

function apply() {
    router.get('/admin/activity-log', {
        user: user.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true });
}

const label = (value) => String(value ?? '').replace(/_/g, ' ');
</script>

<template>
    <Head title="Activity Log" />

    <AdminLayout title="Activity Log" current="activity-log" :breadcrumb="[{ label: 'Account' }]">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <form class="row g-2 align-items-end" @submit.prevent="apply">
                    <div class="col-12 col-md-4">
                        <CFormLabel for="user" class="small mb-1">Staff member</CFormLabel>
                        <CFormSelect id="user" v-model="user" size="sm">
                            <option value="">Everyone</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </CFormSelect>
                    </div>
                    <div class="col-12 col-md-4">
                        <CFormLabel for="type" class="small mb-1">Area</CFormLabel>
                        <CFormSelect id="type" v-model="type" size="sm">
                            <option value="">All areas</option>
                            <option v-for="item in types" :key="item" :value="item">{{ label(item) }}</option>
                        </CFormSelect>
                    </div>
                    <div class="col-12 col-md-3">
                        <CButton type="submit" color="primary" size="sm">Filter</CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="entries.data" :meta="entries.meta" min-width="44rem"
                    empty-title="Nothing recorded yet."
                    empty-body="Stage moves, permission changes and catalogue edits all land here.">
                    <template #cell:description="{ row }">
                        <div>{{ row.description }}</div>
                        <div v-if="row.table" class="code small text-body-secondary">{{ row.table }}</div>
                    </template>
                    <template #cell:type="{ row }"><span class="small">{{ label(row.type) }}</span></template>
                    <template #cell:at="{ row }"><span class="small nowrap">{{ row.at }}</span></template>
                </DataTable>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
