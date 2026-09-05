<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    staff: { type: Object, required: true },
    roles: { type: Object, required: true },
    permissions: { type: Array, required: true },
});

const form = useForm({
    f_name: props.staff.f_name,
    l_name: props.staff.l_name,
    email: props.staff.email,
    phone: props.staff.phone,
    role: props.staff.role,
    status: props.staff.status,
});

const saving = ref(null);

function save() {
    form.put(`/admin/hq-staff/${props.staff.id}`, { preserveScroll: true });
}

function toggle(permission) {
    saving.value = permission.slug;
    router.post(`/admin/hq-staff/${props.staff.id}/permissions`, {
        slug: permission.slug,
        granted: !permission.granted,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = null; },
    });
}
</script>

<template>
    <Head :title="`${staff.f_name} ${staff.l_name}`" />

    <AdminLayout
        :title="`${staff.f_name} ${staff.l_name}`"
        current="hq-staff"
        :breadcrumb="[{ label: 'Account' }, { label: 'HQ Staff' }]"
    >
        <div class="mb-3">
            <Link href="/admin/hq-staff" class="small">← Back to HQ Staff</Link>
        </div>

        <CRow class="g-3">
            <CCol :lg="5">
                <CCard class="border h-100">
                    <CCardHeader class="bg-transparent">
                        <span class="fw-semibold">Staff details</span>
                        <span class="code small text-body-secondary ms-2">{{ staff.reference }}</span>
                    </CCardHeader>
                    <CCardBody>
                        <form class="row g-3" @submit.prevent="save">
                            <div class="col-6">
                                <CFormLabel for="f_name">First name</CFormLabel>
                                <CFormInput id="f_name" v-model="form.f_name" :invalid="Boolean(form.errors.f_name)" />
                            </div>
                            <div class="col-6">
                                <CFormLabel for="l_name">Last name</CFormLabel>
                                <CFormInput id="l_name" v-model="form.l_name" :invalid="Boolean(form.errors.l_name)" />
                            </div>
                            <div class="col-12">
                                <CFormLabel for="email">Email address</CFormLabel>
                                <CFormInput id="email" v-model="form.email" type="email" :invalid="Boolean(form.errors.email)" />
                                <CFormFeedback v-if="form.errors.email" invalid>{{ form.errors.email }}</CFormFeedback>
                            </div>
                            <div class="col-12">
                                <!-- The source used type="email" on this field. -->
                                <CFormLabel for="phone">Phone</CFormLabel>
                                <CFormInput id="phone" v-model="form.phone" type="tel" :invalid="Boolean(form.errors.phone)" />
                            </div>
                            <div class="col-md-6">
                                <CFormLabel for="role">Designation</CFormLabel>
                                <CFormSelect id="role" v-model="form.role">
                                    <option v-for="(label, value) in roles" :key="value" :value="Number(value)">{{ label }}</option>
                                </CFormSelect>
                            </div>
                            <div class="col-md-6">
                                <CFormLabel for="status">Status</CFormLabel>
                                <CFormSelect id="status" v-model="form.status">
                                    <option :value="1">Active</option>
                                    <option :value="0">Inactive</option>
                                    <option :value="2">Banned</option>
                                </CFormSelect>
                            </div>
                            <div class="col-12">
                                <CButton type="submit" color="primary" :disabled="form.processing">
                                    {{ form.processing ? 'Saving…' : 'Save details' }}
                                </CButton>
                            </div>
                        </form>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :lg="7">
                <CCard class="border h-100">
                    <CCardHeader class="bg-transparent">
                        <span class="fw-semibold">Permissions</span>
                        <span class="small text-body-secondary ms-2">
                            {{ permissions.filter((p) => p.granted).length }} of {{ permissions.length }} granted
                        </span>
                    </CCardHeader>
                    <CCardBody class="p-0">
                        <!-- The source painted these lawngreen and indianred, so
                             the state was carried by colour alone. A labelled
                             switch says it in words as well. -->
                        <ul class="list-unstyled mb-0">
                            <li
                                v-for="permission in permissions"
                                :key="permission.slug"
                                class="d-flex align-items-center justify-content-between gap-3 px-3 py-2 border-bottom"
                            >
                                <span>
                                    <span class="d-block">{{ permission.name }}</span>
                                    <span class="code small text-body-secondary">{{ permission.slug }}</span>
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="small" :class="permission.granted ? 'text-success' : 'text-body-secondary'">
                                        {{ permission.granted ? 'Granted' : 'No access' }}
                                    </span>
                                    <CFormSwitch
                                        :model-value="permission.granted"
                                        :disabled="saving === permission.slug"
                                        :aria-label="`${permission.granted ? 'Revoke' : 'Grant'} ${permission.name}`"
                                        @change="toggle(permission)"
                                    />
                                </span>
                            </li>
                        </ul>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>
    </AdminLayout>
</template>
