<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ fields: { type: Array, required: true } });

const form = useForm({
    settings: Object.fromEntries(props.fields.map((f) => [f.key, f.value])),
});

const toggles = props.fields.filter((f) => f.type === 'toggle');
const inputs = props.fields.filter((f) => f.type !== 'toggle');

function submit() {
    form.put('/admin/store-setting', { preserveScroll: true });
}
</script>

<template>
    <Head title="Store Setting" />

    <AdminLayout title="Store Setting" current="store-setting" :breadcrumb="[{ label: 'Settings' }]">
        <form @submit.prevent="submit">
            <CRow class="g-3 align-items-start">
                <CCol :lg="7">
                    <CCard class="border">
                        <CCardHeader class="bg-transparent"><span class="fw-semibold">Storefront details</span></CCardHeader>
                        <CCardBody class="row g-3">
                            <div v-for="field in inputs" :key="field.key" class="col-12">
                                <CFormLabel :for="field.key">{{ field.label }}</CFormLabel>
                                <CFormTextarea v-if="field.type === 'textarea'" :id="field.key" v-model="form.settings[field.key]" rows="3" />
                                <CFormInput v-else :id="field.key" v-model="form.settings[field.key]" :type="field.type" />
                            </div>
                        </CCardBody>
                    </CCard>
                </CCol>

                <CCol :lg="5">
                    <CCard class="border">
                        <CCardHeader class="bg-transparent"><span class="fw-semibold">Checkout options</span></CCardHeader>
                        <CCardBody>
                            <!-- Stored as the string '1'/'0', the way the source
                                 reads them, so nothing downstream has to change. -->
                            <div v-for="field in toggles" :key="field.key"
                                class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                <span>{{ field.label }}</span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="small" :class="form.settings[field.key] === '1' ? 'text-success' : 'text-body-secondary'">
                                        {{ form.settings[field.key] === '1' ? 'On' : 'Off' }}
                                    </span>
                                    <CFormSwitch
                                        :model-value="form.settings[field.key] === '1'"
                                        :aria-label="field.label"
                                        @change="form.settings[field.key] = form.settings[field.key] === '1' ? '0' : '1'"
                                    />
                                </span>
                            </div>
                        </CCardBody>
                    </CCard>
                </CCol>
            </CRow>

            <div class="mt-3">
                <CButton type="submit" color="primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save settings' }}
                </CButton>
            </div>
        </form>
    </AdminLayout>
</template>
