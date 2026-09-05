<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    address: { type: Object, required: true },
    states: { type: Array, default: () => [] },
    country: { type: Object, required: true },
    payments: { type: Object, required: true },
});

const money = (v) => Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const form = useForm({
    first_name: props.address.first_name ?? '',
    last_name: props.address.last_name ?? '',
    address_1: props.address.address_1 ?? '',
    address_2: props.address.address_2 ?? '',
    city: props.address.city ?? '',
    state: props.address.state ?? '',
    postcode: props.address.postcode ?? '',
    phone: props.address.phone ?? '',
    email: props.address.email ?? '',
    remark: props.address.remark ?? '',
    courier_service: props.address.courier_service || 'J&T Express',
    remember: false,
});

const method = ref('normal');

// The figures below always come from the server. Choosing COD re-asks rather
// than adding a fee in the browser, because the browser's number is not the
// number that gets charged.
const codSelected = computed(() => method.value === 'cod');
const payableNow = computed(() => props.summary.total);
</script>

<template>
    <Seo title="Checkout" description="Complete your order." :index="false" />

    <StorefrontLayout current="checkout">
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links">
                    <Link href="/">Home</Link>
                    <Link href="/cart">Cart</Link>
                    <span>Checkout</span>
                </div>
            </div>
        </div>

        <section class="checkout spad">
            <div class="container">
                <div v-if="!summary.items.length" class="text-center py-5">
                    <h4 class="mb-2">Your cart is empty</h4>
                    <Link href="/" class="site-btn">Start shopping</Link>
                </div>

                <div v-else class="row">
                    <div class="col-lg-7">
                        <h5 class="mb-3">Delivery details</h5>

                        <form @submit.prevent="form.post('/checkout/address', { preserveScroll: true })">
                            <div class="row">
                                <div class="col-md-6 checkout__input">
                                    <p>First name<span>*</span></p>
                                    <input v-model="form.first_name" type="text" required>
                                    <small v-if="form.errors.first_name" class="text-danger">{{ form.errors.first_name }}</small>
                                </div>
                                <div class="col-md-6 checkout__input">
                                    <p>Last name<span>*</span></p>
                                    <input v-model="form.last_name" type="text" required>
                                </div>
                            </div>

                            <div class="checkout__input">
                                <p>Country<span>*</span></p>
                                <input type="text" :value="country.name" disabled>
                            </div>

                            <div class="checkout__input">
                                <p>Address<span>*</span></p>
                                <input v-model="form.address_1" type="text" placeholder="Street address" required>
                                <input v-model="form.address_2" type="text" class="mt-2" placeholder="Apartment, suite, unit (optional)">
                                <small v-if="form.errors.address_1" class="text-danger">{{ form.errors.address_1 }}</small>
                            </div>

                            <div class="row">
                                <div class="col-md-4 checkout__input">
                                    <p>Postcode<span>*</span></p>
                                    <input v-model="form.postcode" type="text" required>
                                </div>
                                <div class="col-md-4 checkout__input">
                                    <p>Town / City<span>*</span></p>
                                    <input v-model="form.city" type="text" required>
                                </div>
                                <div class="col-md-4 checkout__input">
                                    <p>State<span>*</span></p>
                                    <select v-if="states.length" v-model="form.state" class="form-select" required>
                                        <option value="">Select state</option>
                                        <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                                    </select>
                                    <input v-else v-model="form.state" type="text" required>
                                    <small v-if="form.errors.state" class="text-danger">{{ form.errors.state }}</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 checkout__input">
                                    <p>Phone<span>*</span></p>
                                    <input v-model="form.phone" type="tel" required>
                                </div>
                                <div class="col-md-6 checkout__input">
                                    <p>Email<span>*</span></p>
                                    <input v-model="form.email" type="email" required>
                                    <small v-if="form.errors.email" class="text-danger">{{ form.errors.email }}</small>
                                </div>
                            </div>

                            <div class="checkout__input">
                                <p>Courier<span>*</span></p>
                                <select v-model="form.courier_service" class="form-select" required>
                                    <option>J&amp;T Express</option>
                                    <option>NinjaVan</option>
                                    <option>DHL eCommerce</option>
                                </select>
                            </div>

                            <div class="checkout__input">
                                <p>Order notes</p>
                                <input v-model="form.remark" type="text" placeholder="Anything we should know about delivery">
                            </div>

                            <label class="d-flex align-items-center gap-2 mb-3">
                                <input v-model="form.remember" type="checkbox">
                                <span class="small">Remember these details on this device for 30 days</span>
                            </label>

                            <button type="submit" class="site-btn" :disabled="form.processing">
                                {{ form.processing ? 'Saving…' : 'Save & calculate postage' }}
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-5">
                        <div class="checkout__order">
                            <h5 class="mb-3">Your order</h5>

                            <ul class="list-unstyled">
                                <li v-for="line in summary.items" :key="line.id" class="d-flex justify-content-between py-1">
                                    <span>{{ line.name }} <small class="text-muted">×{{ line.quantity }}</small></span>
                                    <span>{{ summary.currency }} {{ money(line.line_total) }}</span>
                                </li>
                            </ul>

                            <hr>

                            <div class="d-flex justify-content-between py-1">
                                <span>Subtotal</span><b>{{ summary.currency }} {{ money(summary.subtotal) }}</b>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span>Postage</span>
                                <b v-if="summary.postage_known">{{ summary.currency }} {{ money(summary.postage) }}</b>
                                <span v-else class="text-muted">Enter your address</span>
                            </div>
                            <div v-if="codSelected && summary.postage_known" class="d-flex justify-content-between py-1">
                                <span>Cash on delivery fee</span>
                                <b>{{ summary.currency }} {{ money(summary.cod_fee) }}</b>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between py-1 fs-5">
                                <b>Total</b><b>{{ summary.currency }} {{ money(payableNow) }}</b>
                            </div>

                            <div v-if="summary.postage_known" class="mt-4">
                                <h6 class="mb-2">How would you like to pay?</h6>

                                <label v-if="payments.cod" class="d-flex align-items-center gap-2 py-1">
                                    <input v-model="method" type="radio" value="cod">
                                    <span>Cash on delivery</span>
                                </label>
                                <label v-if="payments.senangpay" class="d-flex align-items-center gap-2 py-1">
                                    <input v-model="method" type="radio" value="senangpay">
                                    <span>Online banking &amp; e-wallet (SenangPay)</span>
                                </label>
                                <label v-if="payments.bayarcash" class="d-flex align-items-center gap-2 py-1">
                                    <input v-model="method" type="radio" value="bayarcash">
                                    <span>FPX, DuitNow &amp; BNPL (Bayarcash)</span>
                                </label>
                                <label v-if="payments.stripe" class="d-flex align-items-center gap-2 py-1">
                                    <input v-model="method" type="radio" value="stripe">
                                    <span>Card (Stripe)</span>
                                </label>

                                <button type="button" class="site-btn w-100 mt-3" disabled>
                                    Payment arrives with Phase 6
                                </button>
                                <p class="small text-muted mt-2 mb-0">
                                    The gateways are the next phase of the migration. Your address and postage are saved.
                                </p>
                            </div>
                            <p v-else class="small text-muted mt-3 mb-0">
                                Save your delivery details to see postage and payment options.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
