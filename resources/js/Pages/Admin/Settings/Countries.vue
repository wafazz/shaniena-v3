<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

defineProps({
    countries: { type: Array, required: true },
    available: { type: Array, required: true },
});

const adding = ref(false);
const editing = ref(null);

const addForm = useForm({ name: '', sign: '', rate: '', phone_code: '' });
const editForm = useForm({ sign: '', rate: '', status: 1 });

const columns = [
    { key: 'name', label: 'Country' },
    { key: 'sign', label: 'Currency' },
    { key: 'rate', label: 'Rate to MYR', align: 'right' },
    { key: 'states', label: 'States', align: 'right' },
    { key: 'status', label: 'Selling' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function pick(event) {
    const chosen = JSON.parse(event.target.value || 'null');
    if (chosen) { addForm.name = chosen.name; addForm.phone_code = chosen.phone_code; }
}

function openEdit(country) {
    editing.value = country;
    editForm.clearErrors();
    editForm.sign = country.sign;
    editForm.rate = country.rate;
    editForm.status = country.status;
}
</script>

<template>
    <Head title="List Country" />

    <AdminLayout title="List Country" current="list-country" :breadcrumb="[{ label: 'Settings' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="primary" size="sm" @click="adding = !adding">{{ adding ? 'Close' : 'Add country' }}</CButton>
        </div>

        <CCard v-if="adding" class="border mb-3">
            <CCardBody class="row g-3">
                <div class="col-md-5">
                    <CFormLabel for="pick">Country</CFormLabel>
                    <CFormSelect id="pick" @change="pick">
                        <option value="">Choose from the world list…</option>
                        <option v-for="c in available" :key="c.id" :value="JSON.stringify(c)">{{ c.name }}</option>
                    </CFormSelect>
                    <CFormFeedback v-if="addForm.errors.name" invalid class="d-block">{{ addForm.errors.name }}</CFormFeedback>
                </div>
                <div class="col-md-2">
                    <CFormLabel for="asign">Currency</CFormLabel>
                    <CFormInput id="asign" v-model="addForm.sign" placeholder="SGD" />
                </div>
                <div class="col-md-2">
                    <CFormLabel for="arate">Rate to MYR</CFormLabel>
                    <CFormInput id="arate" v-model="addForm.rate" type="number" step="0.0001" min="0.0001" :invalid="Boolean(addForm.errors.rate)" />
                </div>
                <div class="col-md-2">
                    <CFormLabel for="acode">Phone code</CFormLabel>
                    <CFormInput id="acode" v-model="addForm.phone_code" class="code" />
                </div>
                <div class="col-12">
                    <CButton color="primary" :disabled="addForm.processing"
                        @click="addForm.post('/admin/countries', { preserveScroll: true, onSuccess: () => { adding = false; addForm.reset(); } })">
                        Add country
                    </CButton>
                    <span class="small text-body-secondary ms-3">Added switched off — set postage first, then start selling.</span>
                </div>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="countries" min-width="44rem" empty-title="No selling countries yet.">
                    <template #cell:name="{ row }">
                        <div>{{ row.name }}</div>
                        <div class="code small text-body-secondary">{{ row.phone_code }}</div>
                    </template>
                    <template #cell:rate="{ row }"><span class="num">{{ Number(row.rate).toFixed(4) }}</span></template>
                    <template #cell:states="{ row }"><span class="num">{{ row.states }}</span></template>
                    <template #cell:status="{ row }">
                        <CBadge :color="row.status === 1 ? 'success' : 'secondary'" shape="rounded-pill">
                            {{ row.status === 1 ? 'Selling' : 'Off' }}
                        </CBadge>
                    </template>
                    <template #cell:actions="{ row }">
                        <div class="d-flex gap-1 justify-content-end">
                            <Link :href="`/admin/countries/${row.id}/states`" class="btn btn-sm btn-outline-secondary">States &amp; zones</Link>
                            <CButton size="sm" color="secondary" variant="outline" @click="openEdit(row)">Edit</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>

        <CModal :visible="Boolean(editing)" @close="editing = null" alignment="center">
            <CModalHeader><CModalTitle>{{ editing?.name }}</CModalTitle></CModalHeader>
            <CModalBody class="row g-3">
                <div class="col-4">
                    <CFormLabel for="esign">Currency</CFormLabel>
                    <CFormInput id="esign" v-model="editForm.sign" />
                </div>
                <div class="col-4">
                    <CFormLabel for="erate">Rate to MYR</CFormLabel>
                    <CFormInput id="erate" v-model="editForm.rate" type="number" step="0.0001" min="0.0001" />
                    <div class="form-text">Fills every new order's stored rate.</div>
                </div>
                <div class="col-4">
                    <CFormLabel for="estatus">Selling</CFormLabel>
                    <CFormSelect id="estatus" v-model="editForm.status">
                        <option :value="1">Yes</option>
                        <option :value="0">No</option>
                    </CFormSelect>
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editing = null">Cancel</CButton>
                <CButton color="primary" :disabled="editForm.processing"
                    @click="editForm.put(`/admin/countries/${editing.id}`, { preserveScroll: true, onSuccess: () => (editing = null) })">
                    Save
                </CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
