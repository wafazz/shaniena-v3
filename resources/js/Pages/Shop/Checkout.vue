<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
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

const CHANNELS = [
    { value: 'cod', label: 'Cash on delivery' },
    { value: 'senangpay', label: 'Online banking & e-wallet (SenangPay)' },
    { value: 'bayarcash', label: 'FPX, DuitNow & BNPL (Bayarcash)' },
    { value: 'billplz', label: 'FPX & e-wallet (Billplz)' },
    { value: 'stripe', label: 'Card (Stripe)' },
];

const method = ref('');
const paying = ref(false);

function pay() {
    if (!method.value) return;

    paying.value = true;
    // The amount is not sent: the server prices the basket again when it
    // creates the order.
    router.post(`/pay/${method.value}`, {}, { onFinish: () => { paying.value = false; } });
}

// The figures below always come from the server. Choosing COD re-asks rather
// than adding a fee in the browser, because the browser's number is not the
// number that gets charged.
const codSelected = computed(() => method.value === 'cod');

// Re-price when the customer picks COD, so the fee shown is the server's.
watch(codSelected, (isCod) => {
    router.reload({ data: { cod: isCod ? 1 : undefined }, only: ['summary'] });
});
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

                        <!-- checkout__form / checkout__form__input are the
                             template's own class names. The markup said
                             checkout__input, so the whole checkout stylesheet
                             was dead and every field rendered as a bare
                             browser box. Labels are real <label for> now, with
                             ids and autocomplete tokens, so a screen reader
                             announces each field and a phone can fill it. -->
                        <form class="checkout__form"
                            @submit.prevent="form.post('/checkout/address', { preserveScroll: true })">
                            <div class="row">
                                <div class="col-md-6 checkout__form__input">
                                    <label for="first_name">First name<span>*</span></label>
                                    <input id="first_name" v-model="form.first_name" name="given-name"
                                        type="text" autocomplete="given-name" required>
                                    <small v-if="form.errors.first_name" class="text-danger">{{ form.errors.first_name }}</small>
                                </div>
                                <div class="col-md-6 checkout__form__input">
                                    <label for="last_name">Last name<span>*</span></label>
                                    <input id="last_name" v-model="form.last_name" name="family-name"
                                        type="text" autocomplete="family-name" required>
                                </div>
                            </div>

                            <div class="checkout__form__input">
                                <label for="country">Country<span>*</span></label>
                                <input id="country" type="text" :value="country.name" disabled>
                            </div>

                            <div class="checkout__form__input">
                                <label for="address_1">Address<span>*</span></label>
                                <input id="address_1" v-model="form.address_1" name="address-line1" type="text"
                                    autocomplete="address-line1" placeholder="Street address" required>
                                <input id="address_2" v-model="form.address_2" name="address-line2" type="text"
                                    autocomplete="address-line2" aria-label="Address line 2"
                                    placeholder="Apartment, suite, unit (optional)">
                                <small v-if="form.errors.address_1" class="text-danger">{{ form.errors.address_1 }}</small>
                            </div>

                            <div class="row">
                                <div class="col-md-4 checkout__form__input">
                                    <label for="postcode">Postcode<span>*</span></label>
                                    <input id="postcode" v-model="form.postcode" name="postal-code" type="text"
                                        inputmode="numeric" autocomplete="postal-code" required>
                                </div>
                                <div class="col-md-4 checkout__form__input">
                                    <label for="city">Town / City<span>*</span></label>
                                    <input id="city" v-model="form.city" name="address-level2" type="text"
                                        autocomplete="address-level2" required>
                                </div>
                                <div class="col-md-4 checkout__form__input">
                                    <label for="state">State<span>*</span></label>
                                    <select v-if="states.length" id="state" v-model="form.state" name="address-level1"
                                        autocomplete="address-level1" required>
                                        <option value="">Select state</option>
                                        <option v-for="state in states" :key="state" :value="state">{{ state }}</option>
                                    </select>
                                    <input v-else id="state" v-model="form.state" name="address-level1" type="text"
                                        autocomplete="address-level1" required>
                                    <small v-if="form.errors.state" class="text-danger">{{ form.errors.state }}</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 checkout__form__input">
                                    <label for="phone">Phone<span>*</span></label>
                                    <input id="phone" v-model="form.phone" name="tel" type="tel"
                                        inputmode="tel" autocomplete="tel" required>
                                </div>
                                <div class="col-md-6 checkout__form__input">
                                    <label for="email">Email<span>*</span></label>
                                    <input id="email" v-model="form.email" name="email" type="email"
                                        inputmode="email" autocomplete="email" required>
                                    <small v-if="form.errors.email" class="text-danger">{{ form.errors.email }}</small>
                                </div>
                            </div>

                            <div class="checkout__form__input">
                                <label for="courier_service">Courier<span>*</span></label>
                                <select id="courier_service" v-model="form.courier_service" name="courier_service" required>
                                    <option>J&amp;T Express</option>
                                    <option>NinjaVan</option>
                                    <option>DHL eCommerce</option>
                                </select>
                            </div>

                            <div class="checkout__form__input">
                                <label for="remark">Order notes</label>
                                <input id="remark" v-model="form.remark" name="remark" type="text"
                                    placeholder="Anything we should know about delivery">
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

                                <label v-for="option in CHANNELS.filter((c) => payments[c.value])" :key="option.value"
                                    class="d-flex align-items-center gap-2 py-1">
                                    <input v-model="method" type="radio" :value="option.value">
                                    <span>{{ option.label }}</span>
                                </label>

                                <p v-if="!Object.values(payments).some(Boolean)" class="small text-muted mb-0">
                                    No payment method is switched on yet. Please get in touch and we'll take the order by hand.
                                </p>

                                <button v-else type="button" class="site-btn w-100 mt-3"
                                    :disabled="!method || paying" @click="pay">
                                    {{ paying ? 'Just a moment…' : `Pay ${summary.currency} ${money(payableNow)}` }}
                                </button>
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
