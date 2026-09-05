<script setup>
import { Link, router } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

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
                                            <input type="number" min="1" :max="line.max_purchase" :value="line.quantity"
                                                class="form-control" style="width: 6rem"
                                                :aria-label="`Quantity for ${line.name}`"
                                                @change="setQty(line, Number($event.target.value))">
                                        </td>
                                        <td class="cart__total">{{ summary.currency }} {{ money(line.line_total) }}</td>
                                        <td class="cart__close">
                                            <button type="button" class="btn btn-link p-0"
                                                :aria-label="`Remove ${line.name}`" @click="remove(line)">×</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="cart__discount">
                            <h6>Order summary</h6>
                            <ul class="list-unstyled">
                                <li class="d-flex justify-content-between py-1">
                                    <span>Subtotal</span>
                                    <b>{{ summary.currency }} {{ money(summary.subtotal) }}</b>
                                </li>
                                <li class="d-flex justify-content-between py-1 text-muted">
                                    <span>Postage</span>
                                    <span>Worked out at checkout</span>
                                </li>
                            </ul>
                            <Link href="/checkout" class="site-btn w-100 text-center d-block mt-3">Checkout</Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
