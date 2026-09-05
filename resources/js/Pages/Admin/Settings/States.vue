<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

const props = defineProps({
    country: { type: Object, required: true },
    states: { type: Array, required: true },
    zones: { type: Array, required: true },
});

const editing = ref(null);
const form = useForm({ id: null, state_code: '', name: '', shipping_zone: 1 });

const columns = [
    { key: 'name', label: 'State' },
    { key: 'state_code', label: 'Code' },
    { key: 'shipping_zone', label: 'Shipping zone' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function open(state = null) {
    editing.value = state ?? 'new';
    form.clearErrors();
    form.id = state?.id ?? null;
    form.state_code = state?.state_code ?? '';
    form.name = state?.name ?? '';
    form.shipping_zone = state?.shipping_zone ?? 1;
}

const zoneLabel = (value) => props.zones.find((z) => z.value === value)?.label ?? `Zone ${value}`;
</script>

<template>
    <Head :title="`${country.name} — states`" />

    <AdminLayout :title="`${country.name} — states & zones`" current="list-country" :breadcrumb="[{ label: 'Settings' }, { label: 'List Country' }]">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <Link href="/admin/list-country" class="small">← Back to countries</Link>
            <CButton color="primary" size="sm" @click="open()">Add state</CButton>
        </div>

        <div class="border-start border-3 ps-3 py-2 mb-3">
            <p class="small mb-0">
                The zone decides both postage and the COD benchmark fee, so it affects what a customer is charged. Set it deliberately.
            </p>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="states" min-width="34rem"
                    empty-title="No states recorded for this country."
                    empty-body="Checkout needs them to work out postage and COD.">
                    <template #cell:state_code="{ row }"><span class="code">{{ row.state_code }}</span></template>
                    <template #cell:shipping_zone="{ row }"><span class="small">{{ zoneLabel(row.shipping_zone) }}</span></template>
                    <template #cell:actions="{ row }">
                        <div class="d-flex justify-content-end">
                            <CButton size="sm" color="secondary" variant="outline" @click="open(row)">Edit</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>

        <CModal :visible="Boolean(editing)" @close="editing = null" alignment="center">
            <CModalHeader><CModalTitle>{{ editing === 'new' ? 'Add state' : 'Edit state' }}</CModalTitle></CModalHeader>
            <CModalBody class="row g-3">
                <div class="col-4">
                    <CFormLabel for="scode">Code</CFormLabel>
                    <CFormInput id="scode" v-model="form.state_code" class="code" maxlength="3" :invalid="Boolean(form.errors.state_code)" />
                </div>
                <div class="col-8">
                    <CFormLabel for="sname">Name</CFormLabel>
                    <CFormInput id="sname" v-model="form.name" :invalid="Boolean(form.errors.name)" />
                </div>
                <div class="col-12">
                    <CFormLabel for="szone">Shipping zone</CFormLabel>
                    <CFormSelect id="szone" v-model="form.shipping_zone">
                        <option v-for="zone in zones" :key="zone.value" :value="zone.value">{{ zone.label }}</option>
                    </CFormSelect>
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editing = null">Cancel</CButton>
                <CButton color="primary" :disabled="form.processing"
                    @click="form.post(`/admin/countries/${country.id}/states`, { preserveScroll: true, onSuccess: () => (editing = null) })">
                    Save state
                </CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
