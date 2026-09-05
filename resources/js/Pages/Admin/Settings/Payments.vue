<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    senangpay: { type: Object, required: true },
    bayarcash: { type: Object, required: true },
    stripe: { type: Object, required: true },
});

const senangpay = useForm({
    type: props.senangpay.type,
    merchant_id: props.senangpay.merchant_id ?? '',
    pro_merchant_id: props.senangpay.pro_merchant_id ?? '',
    secret_key: '',
    pro_secret_key: '',
});

const bayarcash = useForm({
    type: props.bayarcash.type,
    sandbox_api_token: '', sandbox_secret_key: '', sandbox_portal_key: '',
    api_token: '', secret_key: '', portal_key: '',
});

const stripe = useForm({
    publish_key: props.stripe.publish_key ?? '',
    secret_key: '', webhook_secret: '',
});

const hint = (masked) => (masked ? `Set — ends ${masked.slice(-4)}. Leave blank to keep it.` : 'Not set yet.');
</script>

<template>
    <Head title="Payment Settings" />

    <AdminLayout title="Payment Settings" current="payment-setting" :breadcrumb="[{ label: 'Settings' }]">
        <div class="rail-note border-start border-3 ps-3 py-2 mb-4">
            <p class="small mb-0">
                Secrets are never sent back to this page — only whether each one is set, and its last four characters.
                Leave a secret field blank to keep the stored value.
            </p>
        </div>

        <CRow class="g-3 align-items-start">
            <CCol :lg="6">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">SenangPay</span></CCardHeader>
                    <CCardBody class="row g-3">
                        <div class="col-12">
                            <CFormLabel for="sp-type">Mode</CFormLabel>
                            <CFormSelect id="sp-type" v-model="senangpay.type">
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </CFormSelect>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="sp-mid">Sandbox merchant ID</CFormLabel>
                            <CFormInput id="sp-mid" v-model="senangpay.merchant_id" class="code" />
                        </div>
                        <div class="col-6">
                            <CFormLabel for="sp-pmid">Production merchant ID</CFormLabel>
                            <CFormInput id="sp-pmid" v-model="senangpay.pro_merchant_id" class="code" />
                        </div>
                        <div class="col-6">
                            <CFormLabel for="sp-sk">Sandbox secret</CFormLabel>
                            <CFormInput id="sp-sk" v-model="senangpay.secret_key" type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(props.senangpay.secret_key) }}</div>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="sp-psk">Production secret</CFormLabel>
                            <CFormInput id="sp-psk" v-model="senangpay.pro_secret_key" type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(props.senangpay.pro_secret_key) }}</div>
                        </div>
                        <div class="col-12">
                            <CButton color="primary" :disabled="senangpay.processing"
                                @click="senangpay.put('/admin/payment-setting/senangpay', { preserveScroll: true })">
                                {{ senangpay.processing ? 'Saving…' : 'Save SenangPay' }}
                            </CButton>
                        </div>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :lg="6">
                <CCard class="border mb-3">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Stripe</span></CCardHeader>
                    <CCardBody class="row g-3">
                        <div class="col-12">
                            <CFormLabel for="st-pk">Publishable key</CFormLabel>
                            <CFormInput id="st-pk" v-model="stripe.publish_key" class="code" :invalid="Boolean(stripe.errors.publish_key)" />
                        </div>
                        <div class="col-6">
                            <CFormLabel for="st-sk">Secret key</CFormLabel>
                            <CFormInput id="st-sk" v-model="stripe.secret_key" type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(props.stripe.secret_key) }}</div>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="st-wh">Webhook secret</CFormLabel>
                            <CFormInput id="st-wh" v-model="stripe.webhook_secret" type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(props.stripe.webhook_secret) }}</div>
                        </div>
                        <div class="col-12">
                            <CButton color="primary" :disabled="stripe.processing"
                                @click="stripe.put('/admin/payment-setting/stripe', { preserveScroll: true })">
                                {{ stripe.processing ? 'Saving…' : 'Save Stripe' }}
                            </CButton>
                        </div>
                    </CCardBody>
                </CCard>

                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Bayarcash</span></CCardHeader>
                    <CCardBody class="row g-3">
                        <div class="col-12">
                            <CFormLabel for="bc-type">Mode</CFormLabel>
                            <CFormSelect id="bc-type" v-model="bayarcash.type">
                                <option value="sandbox">Sandbox</option>
                                <option value="production">Production</option>
                            </CFormSelect>
                        </div>
                        <div class="col-12">
                            <CFormLabel for="bc-token">{{ bayarcash.type === 'sandbox' ? 'Sandbox' : 'Production' }} API token</CFormLabel>
                            <CFormInput :id="'bc-token'" v-model="bayarcash[bayarcash.type === 'sandbox' ? 'sandbox_api_token' : 'api_token']"
                                type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(bayarcash.type === 'sandbox' ? props.bayarcash.sandbox_api_token : props.bayarcash.api_token) }}</div>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bc-secret">Secret key</CFormLabel>
                            <CFormInput id="bc-secret" v-model="bayarcash[bayarcash.type === 'sandbox' ? 'sandbox_secret_key' : 'secret_key']"
                                type="password" autocomplete="off" />
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bc-portal">Portal key</CFormLabel>
                            <CFormInput id="bc-portal" v-model="bayarcash[bayarcash.type === 'sandbox' ? 'sandbox_portal_key' : 'portal_key']"
                                type="password" autocomplete="off" />
                        </div>
                        <div class="col-12">
                            <CButton color="primary" :disabled="bayarcash.processing"
                                @click="bayarcash.put('/admin/payment-setting/bayarcash', { preserveScroll: true })">
                                {{ bayarcash.processing ? 'Saving…' : 'Save Bayarcash' }}
                            </CButton>
                        </div>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>
    </AdminLayout>
</template>
