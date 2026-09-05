<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

const props = defineProps({
    staff: { type: Array, required: true },
    roles: { type: Object, required: true },
});

const showRegister = ref(false);

const form = useForm({
    f_name: '', l_name: '', email: '', phone: '',
    role: '', password: '', password_confirmation: '',
});

const columns = [
    { key: 'reference', label: 'Staff ID' },
    { key: 'name', label: 'Name' },
    { key: 'role_label', label: 'Designation' },
    { key: 'registered_at', label: 'Registered' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

const STATUS = {
    0: { label: 'Inactive', color: 'secondary' },
    1: { label: 'Active', color: 'success' },
    2: { label: 'Banned', color: 'warning' },
};

function submit() {
    form.post('/admin/hq-staff', {
        preserveScroll: true,
        onSuccess: () => { form.reset(); showRegister.value = false; },
    });
}
</script>

<template>
    <Head title="HQ Staff" />

    <AdminLayout title="HQ Staff" current="hq-staff" :breadcrumb="[{ label: 'Account' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="primary" size="sm" @click="showRegister = !showRegister">
                {{ showRegister ? 'Close' : 'Register new' }}
            </CButton>
        </div>

        <CCard v-if="showRegister" class="border mb-3">
            <CCardHeader class="bg-transparent"><span class="fw-semibold">Register new staff</span></CCardHeader>
            <CCardBody>
                <form class="row g-3" @submit.prevent="submit">
                    <div class="col-md-6">
                        <CFormLabel for="f_name">First name</CFormLabel>
                        <CFormInput id="f_name" v-model="form.f_name" :invalid="Boolean(form.errors.f_name)" />
                        <CFormFeedback v-if="form.errors.f_name" invalid>{{ form.errors.f_name }}</CFormFeedback>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="l_name">Last name</CFormLabel>
                        <CFormInput id="l_name" v-model="form.l_name" :invalid="Boolean(form.errors.l_name)" />
                        <CFormFeedback v-if="form.errors.l_name" invalid>{{ form.errors.l_name }}</CFormFeedback>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="email">Email address</CFormLabel>
                        <CFormInput id="email" v-model="form.email" type="email" :invalid="Boolean(form.errors.email)" />
                        <CFormFeedback v-if="form.errors.email" invalid>{{ form.errors.email }}</CFormFeedback>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="phone">Phone</CFormLabel>
                        <CFormInput id="phone" v-model="form.phone" type="tel" :invalid="Boolean(form.errors.phone)" />
                        <CFormFeedback v-if="form.errors.phone" invalid>{{ form.errors.phone }}</CFormFeedback>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="role">Designation</CFormLabel>
                        <CFormSelect id="role" v-model="form.role" :invalid="Boolean(form.errors.role)">
                            <option value="">Select designation</option>
                            <option v-for="(label, value) in roles" :key="value" :value="value">{{ label }}</option>
                        </CFormSelect>
                        <CFormFeedback v-if="form.errors.role" invalid>{{ form.errors.role }}</CFormFeedback>
                    </div>
                    <div class="col-md-6"></div>
                    <div class="col-md-6">
                        <CFormLabel for="password">Password</CFormLabel>
                        <CFormInput id="password" v-model="form.password" type="password" :invalid="Boolean(form.errors.password)" />
                        <div class="form-text">At least 8 characters, upper and lower case, and a symbol.</div>
                        <CFormFeedback v-if="form.errors.password" invalid>{{ form.errors.password }}</CFormFeedback>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="password_confirmation">Confirm password</CFormLabel>
                        <CFormInput id="password_confirmation" v-model="form.password_confirmation" type="password" />
                    </div>
                    <div class="col-12">
                        <CButton type="submit" color="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Register' }}
                        </CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="staff" empty-title="No staff accounts yet.">
                    <template #cell:reference="{ row }"><span class="code">{{ row.reference }}</span></template>

                    <template #cell:name="{ row }">
                        <div>{{ row.name }}</div>
                        <div class="small text-body-secondary">{{ row.email }}</div>
                    </template>

                    <template #cell:registered_at="{ row }">
                        <span class="small nowrap">{{ row.registered_at }}</span>
                    </template>

                    <template #cell:status="{ row }">
                        <CBadge v-if="row.deleted" color="danger" shape="rounded-pill">Deleted</CBadge>
                        <CBadge v-else :color="STATUS[row.status].color" shape="rounded-pill">{{ STATUS[row.status].label }}</CBadge>
                    </template>

                    <template #cell:actions="{ row }">
                        <div class="d-flex justify-content-end">
                            <Link v-if="!row.deleted && row.id !== 1" :href="`/admin/hq-staff/${row.id}`" class="btn btn-sm btn-outline-secondary">
                                Edit access
                            </Link>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
