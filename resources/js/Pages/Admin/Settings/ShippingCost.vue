<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import MoneyCell from '../../../Components/MoneyCell.vue';

const props = defineProps({
    countries: { type: Array, required: true },
    zones: { type: Array, required: true },
    postage: { type: Array, required: true },
    cod: { type: Array, required: true },
});

const country = ref(props.countries[0]?.id ?? null);
const selected = computed(() => props.countries.find((c) => c.id === country.value) ?? null);

const postageFor = (zone) => props.postage.find((p) => p.country_id === country.value && p.shipping_zone === zone);
const codFor = (zone) => props.cod.find((c) => c.country_id === country.value && c.shipping_zone === String(zone));

const postageForm = useForm({ country_id: null, shipping_zone: null, currency: 'MYR', first_kilo: '', next_kilo: '' });
const codForm = useForm({ country_id: null, shipping_zone: null, benchmark_amount: '', cod_fee_below: '', cod_fee_above: '' });

const editingPostage = ref(null);
const editingCod = ref(null);

function openPostage(zone) {
    const row = postageFor(zone);
    editingPostage.value = zone;
    postageForm.clearErrors();
    postageForm.country_id = country.value;
    postageForm.shipping_zone = zone;
    postageForm.currency = row?.currency ?? selected.value?.sign ?? 'MYR';
    postageForm.first_kilo = row?.first_kilo ?? '';
    postageForm.next_kilo = row?.next_kilo ?? '';
}

function openCod(zone) {
    const row = codFor(zone);
    editingCod.value = zone;
    codForm.clearErrors();
    codForm.country_id = country.value;
    codForm.shipping_zone = String(zone);
    codForm.benchmark_amount = row?.benchmark_amount ?? '';
    codForm.cod_fee_below = row?.cod_fee_below ?? '';
    codForm.cod_fee_above = row?.cod_fee_above ?? '';
}
</script>

<template>
    <Head title="Shipping Cost" />

    <AdminLayout title="Shipping Cost" current="delivery-charge" :breadcrumb="[{ label: 'Settings' }]">
        <CCard class="border mb-3">
            <CCardBody class="py-3">
                <CFormLabel for="country" class="small mb-1">Country</CFormLabel>
                <CFormSelect id="country" v-model="country" size="sm" style="max-width: 22rem">
                    <option v-for="c in countries" :key="c.id" :value="c.id">
                        {{ c.name }}{{ c.active ? '' : ' — not selling' }}
                    </option>
                </CFormSelect>
            </CCardBody>
        </CCard>

        <CRow class="g-3 align-items-start">
            <CCol :lg="6">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Postage</span></CCardHeader>
                    <CCardBody>
                        <p class="small text-body-secondary">First kilo flat, then every additional kilo rounded up to the next whole kilo.</p>
                        <div v-for="zone in zones" :key="zone.value" class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <span class="small">{{ zone.label }}</span>
                            <span class="d-flex align-items-center gap-3">
                                <span v-if="postageFor(zone.value)" class="small">
                                    <MoneyCell :amount="postageFor(zone.value).first_kilo" /> +
                                    <MoneyCell :amount="postageFor(zone.value).next_kilo" />/kg
                                </span>
                                <span v-else class="small text-body-secondary">Not set</span>
                                <CButton size="sm" color="secondary" variant="outline" @click="openPostage(zone.value)">Edit</CButton>
                            </span>
                        </div>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :lg="6">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Cash on delivery</span></CCardHeader>
                    <CCardBody>
                        <p class="small text-body-secondary">An order under the benchmark pays the lower fee; at or above it, the higher one.</p>
                        <div v-for="zone in zones" :key="zone.value" class="d-flex align-items-center justify-content-between py-2 border-bottom">
                            <span class="small">{{ zone.label }}</span>
                            <span class="d-flex align-items-center gap-3">
                                <span v-if="codFor(zone.value)" class="small">
                                    below <MoneyCell :amount="codFor(zone.value).cod_fee_below" /> ·
                                    above <MoneyCell :amount="codFor(zone.value).cod_fee_above" />
                                </span>
                                <span v-else class="small text-body-secondary">Not set</span>
                                <CButton size="sm" color="secondary" variant="outline" @click="openCod(zone.value)">Edit</CButton>
                            </span>
                        </div>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>

        <CModal :visible="editingPostage !== null" @close="editingPostage = null" alignment="center">
            <CModalHeader><CModalTitle>Postage — {{ selected?.name }}, zone {{ editingPostage }}</CModalTitle></CModalHeader>
            <CModalBody class="row g-3">
                <div class="col-4">
                    <CFormLabel for="pcurr">Currency</CFormLabel>
                    <CFormInput id="pcurr" v-model="postageForm.currency" />
                </div>
                <div class="col-4">
                    <CFormLabel for="pfirst">First kilo</CFormLabel>
                    <CFormInput id="pfirst" v-model="postageForm.first_kilo" type="number" step="0.01" min="0" :invalid="Boolean(postageForm.errors.first_kilo)" />
                </div>
                <div class="col-4">
                    <CFormLabel for="pnext">Each extra kilo</CFormLabel>
                    <CFormInput id="pnext" v-model="postageForm.next_kilo" type="number" step="0.01" min="0" />
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editingPostage = null">Cancel</CButton>
                <CButton color="primary" :disabled="postageForm.processing"
                    @click="postageForm.post('/admin/delivery-charge/postage', { preserveScroll: true, onSuccess: () => (editingPostage = null) })">
                    Save postage
                </CButton>
            </CModalFooter>
        </CModal>

        <CModal :visible="editingCod !== null" @close="editingCod = null" alignment="center">
            <CModalHeader><CModalTitle>COD — {{ selected?.name }}, zone {{ editingCod }}</CModalTitle></CModalHeader>
            <CModalBody class="row g-3">
                <div class="col-4">
                    <CFormLabel for="cbench">Benchmark</CFormLabel>
                    <CFormInput id="cbench" v-model="codForm.benchmark_amount" type="number" step="0.01" min="0" />
                </div>
                <div class="col-4">
                    <CFormLabel for="cbelow">Fee below</CFormLabel>
                    <CFormInput id="cbelow" v-model="codForm.cod_fee_below" type="number" step="0.01" min="0" />
                </div>
                <div class="col-4">
                    <CFormLabel for="cabove">Fee at or above</CFormLabel>
                    <CFormInput id="cabove" v-model="codForm.cod_fee_above" type="number" step="0.01" min="0" />
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editingCod = null">Cancel</CButton>
                <CButton color="primary" :disabled="codForm.processing"
                    @click="codForm.post('/admin/delivery-charge/cod', { preserveScroll: true, onSuccess: () => (editingCod = null) })">
                    Save COD charge
                </CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
