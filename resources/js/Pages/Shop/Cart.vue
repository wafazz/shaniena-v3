<script setup>
import { Link, router } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import QuantityStepper from '../../Storefront/QuantityStepper.vue';

const props = defineProps({ summary: { type: Object, required: true } });

const money = (v) => Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function setQty(line, quantity) {
    router.put(`/cart/${line.id}`, { quantity }, { preserveScroll: true });
}

function remove(line) {
    router.delete(`/cart/${line.id}`, { preserveScroll: true });
}
</script>

<template>
    <Seo title="Your cart" description="The items in your basket." :index="false" />

    <StorefrontLayout current="cart">
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links">
                    <Link href="/">Home</Link>
                    <span>Your cart</span>
                </div>
            </div>
        </div>

        <section class="shop-cart spad">
            <div class="container">
                <div v-if="!summary.items.length" class="text-center py-5">
                    <h4 class="mb-2">Your cart is empty</h4>
                    <p class="mb-4">Nothing here yet — have a look at what's new.</p>
                    <Link href="/" class="site-btn">Start shopping</Link>
                </div>

                <div v-else class="row">
                    <div class="col-lg-8">
                        <div class="shop__cart__table">
                            <table>
                                <thead>
                                    <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in summary.items" :key="line.id">
                                        <td class="cart__product__item">
                                            <div class="cart__product__item__title">
                                                <h6>
                                                    <Link v-if="line.slug" :href="`/product/${line.slug}`">{{ line.name }}</Link>
                                                    <template v-else>{{ line.name }}</template>
                                                </h6>
                                                <p v-if="line.variant" class="mb-0">{{ line.variant }}</p>
                                            </div>
                                        </td>
                                        <td class="cart__price">{{ summary.currency }} {{ money(line.unit_price) }}</td>
                                        <td class="cart__quantity">
                                            <QuantityStepper :model-value="line.quantity" :max="line.max_purchase"
                                                :label="`Quantity for ${line.name}`"
                                                @update:model-value="setQty(line, $event)" />
                                        </td>
                                        <td class="cart__total">{{ summary.currency }} {{ money(line.line_total) }}</td>
                                        <td class="cart__close">
                                            <button type="button" class="cart__remove"
                                                :aria-label="`Remove ${line.name}`" @click="remove(line)">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- cart__total__procced, not cart__discount: the
                             latter is Ashion's promo-code box, which styles
                             nothing here and left this panel with no
                             background, padding or type at all. There is no
                             voucher table in the schema, so the promo box it
                             was named after has nothing to be. -->
                        <div class="cart__total__procced">
                            <h6>Order summary</h6>
                            <ul>
                                <li>Subtotal <span>{{ summary.currency }} {{ money(summary.subtotal) }}</span></li>
                                <li class="cart__postage">Postage <span>Worked out at checkout</span></li>
                            </ul>
                            <Link href="/checkout" class="primary-btn">Checkout</Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>

<style scoped>
/* The circle comes from .cart__close span in the theme; this only strips the
   browser's own button chrome from around it. */
.cart__remove {
    background: none;
    border: 0;
    padding: 0;
    line-height: 0;
}

/* The panel renders every <span> as an accent-coloured figure. This one is a
   note about when postage becomes known, not an amount. */
.cart__postage span {
    color: #7a7a7a;
    font-weight: 400;
    font-size: 0.9rem;
}
</style>
