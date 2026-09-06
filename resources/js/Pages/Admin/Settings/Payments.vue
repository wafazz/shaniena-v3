<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    senangpay: { type: Object, required: true },
    bayarcash: { type: Object, required: true },
    stripe: { type: Object, required: true },
    billplz: { type: Object, required: true },
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

const billplz = useForm({
    sandbox_production: props.billplz.sandbox_production,
    bill_collection_id: props.billplz.bill_collection_id ?? '',
    payment_collection_slug: props.billplz.payment_collection_slug ?? '',
    bill_charge: props.billplz.bill_charge ?? 0,
    payment_charge: props.billplz.payment_charge,
    api_key: '',
    x_signature: '',
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

            <CCol :lg="6">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Billplz</span></CCardHeader>
                    <CCardBody class="row g-3">
                        <div class="col-6">
                            <CFormLabel for="bp-mode">Mode</CFormLabel>
                            <CFormSelect id="bp-mode" v-model.number="billplz.sandbox_production">
                                <option :value="0">Sandbox</option>
                                <option :value="1">Production</option>
                            </CFormSelect>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bp-collection">Collection ID</CFormLabel>
                            <CFormInput id="bp-collection" v-model="billplz.bill_collection_id" class="code"
                                :invalid="Boolean(billplz.errors.bill_collection_id)" />
                            <div class="form-text">The Billplz collection bills are created in.</div>
                        </div>
                        <div class="col-12">
                            <CFormLabel>Endpoint</CFormLabel>
                            <div class="code small text-body-secondary">{{ props.billplz.endpoint }}</div>
                            <div class="form-text">
                                Fixed by the mode above. It is where the API key is sent, so it is not editable.
                            </div>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bp-key">API secret key</CFormLabel>
                            <CFormInput id="bp-key" v-model="billplz.api_key" type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(props.billplz.api_key) }}</div>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bp-sig">X-Signature key</CFormLabel>
                            <CFormInput id="bp-sig" v-model="billplz.x_signature" type="password" autocomplete="off" />
                            <div class="form-text">{{ hint(props.billplz.x_signature) }}</div>
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bp-charge">FPX fee (RM)</CFormLabel>
                            <CFormInput id="bp-charge" v-model.number="billplz.bill_charge" type="number" step="0.10" min="0"
                                :invalid="Boolean(billplz.errors.bill_charge)" />
                        </div>
                        <div class="col-6">
                            <CFormLabel for="bp-who">Fee paid by</CFormLabel>
                            <CFormSelect id="bp-who" v-model.number="billplz.payment_charge">
                                <option :value="1">Us — absorbed</option>
                                <option :value="2">Customer — added to the bill</option>
                            </CFormSelect>
                        </div>
                        <div class="col-12">
                            <CFormLabel for="bp-slug">Payment collection slug</CFormLabel>
                            <CFormInput id="bp-slug" v-model="billplz.payment_collection_slug" class="code" />
                            <div class="form-text">Optional — the public collection page, if you use one.</div>
                        </div>
                        <div class="col-12">
                            <CButton color="primary" :disabled="billplz.processing"
                                @click="billplz.put('/admin/payment-setting/billplz', { preserveScroll: true })">
                                {{ billplz.processing ? 'Saving…' : 'Save Billplz' }}
                            </CButton>
                        </div>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>
    </AdminLayout>
</template>
