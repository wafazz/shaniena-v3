<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ settings: { type: Object, required: true } });

const form = useForm({
    production_sandbox: props.settings.production_sandbox,
    clientid: props.settings.clientid ?? '',
    clientid_test: props.settings.clientid_test ?? '',
    url: props.settings.url ?? '',
    url_test: props.settings.url_test ?? '',
    password: '',
    password_test: '',
});

const hint = (masked) => (masked ? `Set — ends ${masked.slice(-4)}. Leave blank to keep it.` : 'Not set yet.');
</script>

<template>
    <Head title="DHL Setting" />

    <AdminLayout title="DHL Setting" current="dhl-setting" :breadcrumb="[{ label: 'Settings' }]">
        <CCard class="border" style="max-width: 46rem">
            <CCardBody>
                <form class="row g-3" @submit.prevent="form.put('/admin/dhl-setting', { preserveScroll: true })">
                    <div class="col-12">
                        <CFormLabel for="mode">Mode</CFormLabel>
                        <!-- DHL stores 1 = production, 2 = sandbox. J&T stores
                             the opposite; both are kept as the source defines them. -->
                        <CFormSelect id="mode" v-model="form.production_sandbox">
                            <option :value="2">Sandbox</option>
                            <option :value="1">Production</option>
                        </CFormSelect>
                    </div>

                    <div class="col-md-6">
                        <CFormLabel for="cid-test">Sandbox client ID</CFormLabel>
                        <CFormInput id="cid-test" v-model="form.clientid_test" class="code" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="cid">Production client ID</CFormLabel>
                        <CFormInput id="cid" v-model="form.clientid" class="code" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>

                    <div class="col-md-6">
                        <CFormLabel for="pw-test">Sandbox password</CFormLabel>
                        <CFormInput id="pw-test" v-model="form.password_test" type="password" autocomplete="new-password" data-1p-ignore data-lpignore="true" />
                        <div class="form-text">{{ hint(props.settings.password_test) }}</div>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="pw">Production password</CFormLabel>
                        <CFormInput id="pw" v-model="form.password" type="password" autocomplete="new-password" data-1p-ignore data-lpignore="true" />
                        <div class="form-text">{{ hint(props.settings.password) }}</div>
                    </div>

                    <div class="col-md-6">
                        <CFormLabel for="url-test">Sandbox endpoint</CFormLabel>
                        <CFormInput id="url-test" v-model="form.url_test" class="code" :invalid="Boolean(form.errors.url_test)" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="url">Production endpoint</CFormLabel>
                        <CFormInput id="url" v-model="form.url" class="code" :invalid="Boolean(form.errors.url)" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>

                    <div class="col-12">
                        <CButton type="submit" color="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save DHL settings' }}
                        </CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
