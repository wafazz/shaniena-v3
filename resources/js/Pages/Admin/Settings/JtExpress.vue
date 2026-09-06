<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({ settings: { type: Object, required: true } });

const form = useForm({
    production_sandbox: props.settings.production_sandbox,
    url_sandbox: props.settings.url_sandbox ?? '',
    username_sanbox: props.settings.username_sanbox ?? '',
    cuscode_sandbox: props.settings.cuscode_sandbox ?? '',
    url_production: props.settings.url_production ?? '',
    username_production: props.settings.username_production ?? '',
    cuscode_production: props.settings.cuscode_production ?? '',
    password_sandbox: '', key_sandbox: '', password_production: '', key_production: '',
});

const live = computed(() => form.production_sandbox === 1);
const hint = (masked) => (masked ? `Set — ends ${masked.slice(-4)}. Leave blank to keep it.` : 'Not set yet.');
</script>

<template>
    <Head title="J&T Express" />

    <AdminLayout title="J&amp;T Express" current="jt-express" :breadcrumb="[{ label: 'Settings' }]">
        <CCard class="border" style="max-width: 46rem">
            <CCardBody>
                <form class="row g-3" @submit.prevent="form.put('/admin/jt-express', { preserveScroll: true })">
                    <div class="col-12">
                        <CFormLabel for="jt-mode">Mode</CFormLabel>
                        <!-- J&T stores 0 = sandbox, 1 = production. -->
                        <CFormSelect id="jt-mode" v-model="form.production_sandbox">
                            <option :value="0">Sandbox</option>
                            <option :value="1">Production</option>
                        </CFormSelect>
                        <div class="form-text">Editing the {{ live ? 'production' : 'sandbox' }} credentials below.</div>
                    </div>

                    <div class="col-md-6">
                        <CFormLabel for="jt-url">Endpoint</CFormLabel>
                        <CFormInput id="jt-url" v-model="form[live ? 'url_production' : 'url_sandbox']" class="code" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="jt-user">Username</CFormLabel>
                        <!-- `username_sanbox` is the source's own spelling. -->
                        <CFormInput id="jt-user" v-model="form[live ? 'username_production' : 'username_sanbox']" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="jt-cus">Customer code</CFormLabel>
                        <CFormInput id="jt-cus" v-model="form[live ? 'cuscode_production' : 'cuscode_sandbox']" class="code" autocomplete="off" data-1p-ignore data-lpignore="true" />
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="jt-pw">Password</CFormLabel>
                        <CFormInput id="jt-pw" v-model="form[live ? 'password_production' : 'password_sandbox']" type="password" autocomplete="new-password" data-1p-ignore data-lpignore="true" />
                        <div class="form-text">{{ hint(live ? props.settings.password_production : props.settings.password_sandbox) }}</div>
                    </div>
                    <div class="col-md-6">
                        <CFormLabel for="jt-key">API key</CFormLabel>
                        <CFormInput id="jt-key" v-model="form[live ? 'key_production' : 'key_sandbox']" type="password" autocomplete="new-password" data-1p-ignore data-lpignore="true" />
                        <div class="form-text">{{ hint(live ? props.settings.key_production : props.settings.key_sandbox) }}</div>
                    </div>

                    <div class="col-12">
                        <CButton type="submit" color="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save J&T settings' }}
                        </CButton>
                    </div>
                </form>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
