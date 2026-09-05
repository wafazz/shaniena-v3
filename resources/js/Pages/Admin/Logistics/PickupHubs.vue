<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

defineProps({ hubs: { type: Array, required: true } });

const editing = ref(null);
const form = useForm({
    hub_code: '', hub_name: '', contact_person: '', phone: '', email: '',
    address: '', city: '', state: '', postcode: '', status: 'active',
});

const columns = [
    { key: 'hub_name', label: 'Hub' },
    { key: 'contact', label: 'Contact' },
    { key: 'where', label: 'Location' },
    { key: 'staff', label: 'Staff', align: 'right' },
    { key: 'orders', label: 'Orders', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function open(hub = null) {
    editing.value = hub ?? 'new';
    form.clearErrors();
    Object.keys(form.data()).forEach((key) => { form[key] = hub?.[key] ?? (key === 'status' ? 'active' : ''); });
}

function submit() {
    if (editing.value === 'new') {
        form.post('/admin/pickup-hub', { preserveScroll: true, onSuccess: () => { editing.value = null; } });
    } else {
        form.put(`/admin/pickup-hub/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
    }
}
</script>

<template>
    <Head title="Pickup Hubs" />

    <AdminLayout title="Pickup Hubs" current="pickup-hub" :breadcrumb="[{ label: 'Settings' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="primary" size="sm" @click="open()">Add hub</CButton>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="hubs" min-width="52rem"
                    empty-title="No pickup hubs yet." empty-body="Hubs let customers collect an order instead of having it shipped.">
                    <template #cell:hub_name="{ row }">
                        <div>{{ row.hub_name }}</div>
                        <div class="code small text-body-secondary">{{ row.hub_code }}</div>
                    </template>
                    <template #cell:contact="{ row }">
                        <div class="small">{{ row.contact_person ?? '—' }}</div>
                        <div class="small text-body-secondary code">{{ row.phone ?? '' }}</div>
                    </template>
                    <template #cell:where="{ row }">
                        <span class="small">{{ [row.city, row.state, row.postcode].filter(Boolean).join(', ') || '—' }}</span>
                    </template>
                    <template #cell:staff="{ row }"><span class="num">{{ row.staff }}</span></template>
                    <template #cell:orders="{ row }"><span class="num">{{ row.orders }}</span></template>
                    <template #cell:status="{ row }">
                        <CBadge :color="row.status === 'active' ? 'success' : 'secondary'" shape="rounded-pill" class="text-uppercase">{{ row.status }}</CBadge>
                    </template>
                    <template #cell:actions="{ row }">
                        <div class="d-flex justify-content-end">
                            <CButton size="sm" color="secondary" variant="outline" @click="open(row)">Edit</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>

        <CModal :visible="Boolean(editing)" @close="editing = null" alignment="center">
            <CModalHeader><CModalTitle>{{ editing === 'new' ? 'Add pickup hub' : 'Edit pickup hub' }}</CModalTitle></CModalHeader>
            <CModalBody class="row g-3">
                <div class="col-4">
                    <CFormLabel for="hcode">Hub code</CFormLabel>
                    <CFormInput id="hcode" v-model="form.hub_code" class="code" :invalid="Boolean(form.errors.hub_code)" />
                    <CFormFeedback v-if="form.errors.hub_code" invalid>{{ form.errors.hub_code }}</CFormFeedback>
                </div>
                <div class="col-8">
                    <CFormLabel for="hname">Hub name</CFormLabel>
                    <CFormInput id="hname" v-model="form.hub_name" :invalid="Boolean(form.errors.hub_name)" />
                </div>
                <div class="col-6">
                    <CFormLabel for="hcontact">Contact person</CFormLabel>
                    <CFormInput id="hcontact" v-model="form.contact_person" />
                </div>
                <div class="col-6">
                    <CFormLabel for="hphone">Phone</CFormLabel>
                    <CFormInput id="hphone" v-model="form.phone" type="tel" />
                </div>
                <div class="col-12">
                    <CFormLabel for="hemail">Email</CFormLabel>
                    <CFormInput id="hemail" v-model="form.email" type="email" :invalid="Boolean(form.errors.email)" />
                </div>
                <div class="col-12">
                    <CFormLabel for="haddress">Address</CFormLabel>
                    <CFormTextarea id="haddress" v-model="form.address" rows="2" />
                </div>
                <div class="col-5">
                    <CFormLabel for="hcity">City</CFormLabel>
                    <CFormInput id="hcity" v-model="form.city" />
                </div>
                <div class="col-4">
                    <CFormLabel for="hstate">State</CFormLabel>
                    <CFormInput id="hstate" v-model="form.state" />
                </div>
                <div class="col-3">
                    <CFormLabel for="hpost">Postcode</CFormLabel>
                    <CFormInput id="hpost" v-model="form.postcode" class="code" />
                </div>
                <div class="col-6">
                    <CFormLabel for="hstatus">Status</CFormLabel>
                    <CFormSelect id="hstatus" v-model="form.status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </CFormSelect>
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editing = null">Cancel</CButton>
                <CButton color="primary" :disabled="form.processing" @click="submit">{{ form.processing ? 'Saving…' : 'Save hub' }}</CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
