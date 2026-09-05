<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StatusPill from './StatusPill.vue';

/**
 * One order, in full, in a slide-over.
 *
 * The source rendered this block inline for every row on the page — four raw
 * queries per variant per order — so a 100-row queue ran several hundred
 * queries to draw detail nobody had opened yet.
 */
const props = defineProps({
    orderId: { type: [Number, null], default: null },
});

const emit = defineEmits(['close']);

const order = ref(null);
const loading = ref(false);
const editing = ref(false);

const form = useForm({
    customer_name: '',
    customer_name_last: '',
    customer_phone: '',
    customer_email: '',
    address_1: '',
    address_2: '',
    city: '',
    state: '',
    postcode: '',
    remark_comment: '',
});

const visible = computed(() => props.orderId !== null);

const money = (v, currency = 'RM') =>
    `${currency} ${Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

watch(() => props.orderId, async (id) => {
    order.value = null;
    editing.value = false;
    form.clearErrors();

    if (id === null) return;

    loading.value = true;

    try {
        const response = await fetch(`/admin/orders/${id}/detail`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        order.value = response.ok ? await response.json() : null;
    } finally {
        loading.value = false;
    }
}, { immediate: true });

function startEditing() {
    form.customer_name = order.value.customer.first_name;
    form.customer_name_last = order.value.customer.last_name;
    form.customer_phone = order.value.customer.phone;
    form.customer_email = order.value.customer.email;
    form.address_1 = order.value.address.address_1;
    form.address_2 = order.value.address.address_2;
    form.city = order.value.address.city;
    form.state = order.value.address.state;
    form.postcode = order.value.address.postcode;
    form.remark_comment = order.value.remark ?? '';
    editing.value = true;
}

// Malaysian postcodes decide the town and state outright, so fill them in
// rather than letting an operator type a mismatch the courier will reject.
async function fillFromPostcode() {
    const postcode = String(form.postcode ?? '').replace(/\D/g, '');
    if (postcode.length !== 5) return;

    const response = await fetch(`/admin/orders/postcode?postcode=${postcode}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) return;

    const match = await response.json();
    if (match.city) form.city = match.city;
    if (match.state) form.state = match.state;
}

function save() {
    form.patch(`/admin/orders/${props.orderId}/detail`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
            // Re-read rather than patch locally: the server decides what stuck.
            router.reload({ only: ['orders'] });
            const id = props.orderId;
            order.value = null;
            fetch(`/admin/orders/${id}/detail`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => { order.value = data; });
        },
    });
}
</script>

<template>
    <COffcanvas placement="end" :visible="visible" class="order-panel" @hide="emit('close')">
        <COffcanvasHeader class="border-bottom">
            <COffcanvasTitle class="num">
                {{ order ? order.reference : 'Loading…' }}
            </COffcanvasTitle>
            <CCloseButton class="text-reset" @click="emit('close')" />
        </COffcanvasHeader>

        <COffcanvasBody>
            <div v-if="loading" class="text-body-secondary small">Loading order…</div>

            <div v-else-if="!order" class="text-body-secondary small">
                That order could not be loaded.
            </div>

            <template v-else>
                <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                    <StatusPill :status="order.status" />
                    <span class="small text-body-secondary">Placed {{ order.placed_at }}</span>
                </div>

                <!-- What was bought. The reason anyone opens this panel. -->
                <h6 class="section-label">Items</h6>
                <table class="table table-sm align-middle mb-2">
                    <tbody>
                        <tr v-for="line in order.lines" :key="line.id">
                            <td>
                                <div>{{ line.name }}</div>
                                <div v-if="line.variant" class="small text-body-secondary">
                                    {{ line.variant }}<span v-if="line.sku"> · {{ line.sku }}</span>
                                </div>
                            </td>
                            <td class="text-center num" style="width:3.5rem">&times;{{ line.quantity }}</td>
                            <td class="text-end num" style="width:7rem">{{ money(line.line_total, order.money.currency) }}</td>
                        </tr>
                    </tbody>
                </table>

                <dl class="totals mb-4">
                    <dt>Items</dt><dd class="num">{{ money(order.money.items, order.money.currency) }}</dd>
                    <dt>Postage</dt><dd class="num">{{ money(order.money.postage, order.money.currency) }}</dd>
                    <dt class="fw-semibold">Total</dt>
                    <dd class="num fw-semibold">{{ money(order.money.total, order.money.currency) }}</dd>
                </dl>

                <!-- Delivery. Editable, because a wrong address is the single
                     most common reason a parcel comes back. -->
                <div class="d-flex align-items-baseline justify-content-between">
                    <h6 class="section-label mb-0">Delivery</h6>
                    <CButton v-if="!editing" size="sm" color="secondary" variant="outline" @click="startEditing">
                        Edit
                    </CButton>
                </div>

                <CAlert v-if="editing && order.address_is_with_courier" color="warning" class="small mt-2 mb-2">
                    AWB {{ order.shipping.awb }} is already booked. Changing the address here
                    will not reach the courier — they still have the old one.
                </CAlert>

                <form v-if="editing" class="mt-2" @submit.prevent="save">
                    <CRow class="g-2">
                        <CCol :sm="6">
                            <CFormLabel class="small mb-1">First name</CFormLabel>
                            <CFormInput v-model="form.customer_name" size="sm" :invalid="!!form.errors.customer_name" />
                            <div v-if="form.errors.customer_name" class="invalid-feedback d-block">{{ form.errors.customer_name }}</div>
                        </CCol>
                        <CCol :sm="6">
                            <CFormLabel class="small mb-1">Last name</CFormLabel>
                            <CFormInput v-model="form.customer_name_last" size="sm" />
                        </CCol>
                        <CCol :sm="6">
                            <CFormLabel class="small mb-1">Phone</CFormLabel>
                            <CFormInput v-model="form.customer_phone" size="sm" :invalid="!!form.errors.customer_phone" />
                            <div v-if="form.errors.customer_phone" class="invalid-feedback d-block">{{ form.errors.customer_phone }}</div>
                        </CCol>
                        <CCol :sm="6">
                            <CFormLabel class="small mb-1">Email</CFormLabel>
                            <CFormInput v-model="form.customer_email" type="email" size="sm" :invalid="!!form.errors.customer_email" />
                            <div v-if="form.errors.customer_email" class="invalid-feedback d-block">{{ form.errors.customer_email }}</div>
                        </CCol>
                        <CCol :sm="12">
                            <CFormLabel class="small mb-1">Address</CFormLabel>
                            <CFormInput v-model="form.address_1" size="sm" class="mb-1" :invalid="!!form.errors.address_1" />
                            <CFormInput v-model="form.address_2" size="sm" placeholder="Line 2 (optional)" />
                            <div v-if="form.errors.address_1" class="invalid-feedback d-block">{{ form.errors.address_1 }}</div>
                        </CCol>
                        <CCol :sm="4">
                            <CFormLabel class="small mb-1">Postcode</CFormLabel>
                            <CFormInput v-model="form.postcode" size="sm" inputmode="numeric"
                                :invalid="!!form.errors.postcode" @blur="fillFromPostcode" />
                        </CCol>
                        <CCol :sm="8">
                            <CFormLabel class="small mb-1">Town</CFormLabel>
                            <CFormInput v-model="form.city" size="sm" :invalid="!!form.errors.city" />
                        </CCol>
                        <CCol :sm="12">
                            <CFormLabel class="small mb-1">State</CFormLabel>
                            <CFormInput v-model="form.state" size="sm" list="order-states" :invalid="!!form.errors.state" />
                            <datalist id="order-states">
                                <option v-for="s in order.states" :key="s" :value="s" />
                            </datalist>
                        </CCol>
                        <CCol :sm="12">
                            <CFormLabel class="small mb-1">Internal note</CFormLabel>
                            <CFormTextarea v-model="form.remark_comment" rows="2" size="sm" />
                        </CCol>
                    </CRow>

                    <div class="d-flex gap-2 mt-3">
                        <CButton type="submit" size="sm" color="primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save changes' }}
                        </CButton>
                        <CButton size="sm" color="secondary" variant="outline" @click="editing = false">Cancel</CButton>
                    </div>
                </form>

                <address v-else class="small mb-4 mt-2">
                    <span class="fw-semibold">{{ order.customer.first_name }} {{ order.customer.last_name }}</span><br>
                    {{ order.address.address_1 }}<br>
                    <template v-if="order.address.address_2">{{ order.address.address_2 }}<br></template>
                    <span class="num">{{ order.address.postcode }}</span> {{ order.address.city }}<br>
                    {{ order.address.state }}, {{ order.address.country }}<br>
                    <span class="num">{{ order.customer.phone }}</span><br>
                    {{ order.customer.email }}
                </address>

                <h6 class="section-label">Payment</h6>
                <dl class="pairs mb-4">
                    <dt>Channel</dt><dd class="text-capitalize">{{ order.payment.channel }}</dd>
                    <template v-if="order.payment.code">
                        <dt>Code</dt><dd class="num">{{ order.payment.code }}</dd>
                    </template>
                    <dt>Settled</dt><dd>{{ order.payment.paid ? 'Yes' : 'Awaiting payment' }}</dd>
                </dl>

                <h6 class="section-label">Shipping</h6>
                <dl class="pairs mb-0">
                    <dt>Courier</dt><dd>{{ order.shipping.courier ?? 'Not chosen' }}</dd>
                    <dt>AWB</dt>
                    <dd>
                        <a v-if="order.shipping.tracking_url" :href="order.shipping.tracking_url"
                            target="_blank" rel="noopener" class="num">{{ order.shipping.awb }}</a>
                        <span v-else class="num">{{ order.shipping.awb ?? 'Not booked' }}</span>
                    </dd>
                    <template v-if="order.shipping.milestone">
                        <dt>Last scan</dt><dd>{{ order.shipping.milestone }}</dd>
                    </template>
                    <dt>Label</dt><dd>{{ order.shipping.printed ? 'Printed' : 'Not printed' }}</dd>
                </dl>

                <template v-if="order.remark && !editing">
                    <h6 class="section-label mt-4">Internal note</h6>
                    <p class="small mb-0">{{ order.remark }}</p>
                </template>
            </template>
        </COffcanvasBody>
    </COffcanvas>
</template>

<style scoped>
.order-panel {
    width: min(30rem, 100vw);
}

.section-label {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--cui-secondary-color);
    margin-bottom: .5rem;
}

/* Label/value pairs on one grid so values line up down the column. */
.pairs,
.totals {
    display: grid;
    grid-template-columns: 8rem 1fr;
    gap: .25rem 1rem;
    margin: 0;
    font-size: .875rem;
}

.totals {
    grid-template-columns: 1fr auto;
    border-top: 1px solid var(--cui-border-color);
    padding-top: .5rem;
}

.pairs dt,
.totals dt {
    color: var(--cui-secondary-color);
    font-weight: 400;
}

.pairs dd,
.totals dd {
    margin: 0;
}

/* .num right-aligns globally, which is right in a table column and wrong in a
   label/value pair — it left the AWB and payment code floating alone. */
.pairs dd.num,
.pairs dd .num {
    text-align: left;
}

.totals dd {
    text-align: right;
}
</style>
