<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';
import ProductCard from '../../Storefront/ProductCard.vue';
import QuantityStepper from '../../Storefront/QuantityStepper.vue';
import { useCart } from '../../Storefront/useCart';

const props = defineProps({
    product: { type: Object, required: true },
    variants: { type: Array, required: true },
    defaultVariantId: { type: Number, default: null },
    related: { type: Array, default: () => [] },
});

// Opens on the first in-stock variant, falling back to the first one when
// none are — the source's behaviour, decided server-side.
const selectedId = ref(props.defaultVariantId ?? props.variants[0]?.id ?? null);
const selected = computed(() => props.variants.find((v) => v.id === selectedId.value) ?? null);

const inStock = computed(() => (selected.value?.stock ?? 0) > 0);
const maxQty = computed(() => Math.max(1, Math.min(selected.value?.stock ?? 1, selected.value?.max_purchase ?? 1)));

const qty = ref(1);
watch(selected, () => { qty.value = 1; });

const activeImage = ref(0);
const images = computed(() => (props.product.images.length ? props.product.images : [null]));

// Held for the length of the fade so the picture changes rather than
// substituting one set of pixels for another mid-blink.
const swapping = ref(false);

function showImage(index) {
    if (index === activeImage.value) {
        return;
    }

    swapping.value = true;
    setTimeout(() => {
        activeImage.value = index;
        swapping.value = false;
    }, 130);
}

const { cart, add } = useCart();

const adding = computed(() => cart.adding === selectedId.value);
const added = computed(() => cart.justAdded === selectedId.value);
const error = ref(null);

// The error is the only thing left to say on the page: what went *right* is
// said by the basket sliding open with the item in it.
function addToCart() {
    if (!inStock.value || adding.value) {
        return;
    }

    error.value = null;

    add(
        { productId: props.product.id, variantId: selectedId.value, quantity: qty.value },
        { onError: (message) => { error.value = message; } },
    );
}
</script>

<template>
    <Seo
        :title="product.name"
        :description="(product.description || `${product.name} from ${product.brand?.name ?? 'Shaniena'}.`).slice(0, 160)"
        :image="product.images[0] ?? null"
        type="product"
    />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links">
                    <Link href="/">Home</Link>
                    <Link v-if="product.category" :href="`/categories/${product.category.slug ?? product.category.id}`">
                        {{ product.category.name }}
                    </Link>
                    <span>{{ product.name }}</span>
                </div>
            </div>
        </div>

        <section class="product-details spad">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="product__details__pic">
                            <div class="product__details__pic__item" :class="{ 'is-swapping': swapping }">
                                <img v-if="images[activeImage]" :src="images[activeImage]" :alt="product.name">
                                <div v-else class="product__details__placeholder">No photo yet</div>
                            </div>
                            <div v-if="product.images.length > 1" class="d-flex gap-2 mt-3 flex-wrap">
                                <button v-for="(image, index) in product.images" :key="index" type="button"
                                    class="product__thumb-btn" :class="{ active: index === activeImage }"
                                    :aria-label="`View image ${index + 1}`" @click="showImage(index)">
                                    <img :src="image" :alt="`${product.name} image ${index + 1}`">
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="product__details__text">
                            <h3>
                                {{ product.name }}
                                <span v-if="product.brand">Brand: {{ product.brand.name }}</span>
                            </h3>

                            <div class="product__details__price">
                                <template v-if="product.price">
                                    {{ product.currency }} {{ product.price }}
                                    <span v-if="product.was">{{ product.currency }} {{ product.was }}</span>
                                </template>
                                <template v-else>Price on request</template>
                            </div>

                            <div v-if="variants.length > 1" class="mb-3">
                                <label for="variant" class="d-block mb-1">Variant</label>
                                <select id="variant" v-model="selectedId" class="form-select" style="max-width: 20rem">
                                    <!-- Out-of-stock variants are shown but not
                                         selectable; the source left them clickable
                                         and then disabled the button afterwards. -->
                                    <option v-for="variant in variants" :key="variant.id" :value="variant.id"
                                        :disabled="variant.stock < 1">
                                        {{ variant.name }}<template v-if="variant.stock < 1"> — out of stock</template>
                                    </option>
                                </select>
                            </div>

                            <div v-if="inStock" class="mb-3 d-flex align-items-end gap-3 flex-wrap">
                                <div>
                                    <span class="d-block mb-1">Quantity</span>
                                    <QuantityStepper v-model="qty" :max="maxQty" :disabled="adding" />
                                </div>
                                <button type="button" class="site-btn" :disabled="adding" @click="addToCart">
                                    <template v-if="adding">Adding…</template>
                                    <template v-else-if="added">Added ✓</template>
                                    <template v-else>Add to cart</template>
                                </button>
                            </div>

                            <p v-if="inStock && maxQty < (selected?.stock ?? 0)" class="text-muted small">
                                Limit {{ maxQty }} per order.
                            </p>

                            <p v-if="error" class="text-danger add-notice">{{ error }}</p>

                            <ul class="mt-4">
                                <!-- The whitespace between <b> and the value is
                                     collapsed away when the value sits on its own
                                     line behind a v-if, so it read
                                     "AvailabilityIn stock (270)". -->
                                <li>
                                    <b>Availability</b>{{ ' ' }}
                                    <span v-if="inStock">In stock ({{ selected.stock }})</span>
                                    <span v-else class="text-danger">Out of stock</span>
                                </li>
                                <li v-if="selected?.sku"><b>SKU</b> <span>{{ selected.sku }}</span></li>
                                <li><b>Delivery</b> <span>3–7 working days within Malaysia, 7–14 days overseas</span></li>
                                <li><b>Returns</b> <span>Free and easy returns within 14 days of delivery</span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div v-if="product.description" class="row mt-5">
                    <div class="col-lg-12">
                        <div class="section-title"><h4>Description</h4></div>
                        <p style="white-space: pre-wrap">{{ product.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="related.length" class="product spad pt-0">
            <div class="container">
                <div class="section-title"><h4>You may also like</h4></div>
                <div class="row">
                    <div v-for="item in related" :key="item.id" class="col-lg-3 col-md-4 col-sm-6">
                        <ProductCard :product="item" />
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>

<style scoped>
.product__details__placeholder {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    color: #9a9a9a;
    text-transform: uppercase;
    font-size: 13px;
    letter-spacing: 0.04em;
}

.product__thumb-btn {
    border: 1px solid #ebebeb;
    background: none;
    padding: 0;
    width: 72px;
    height: 72px;
    overflow: hidden;
}

.product__thumb-btn.active {
    border-color: #ca1515;
}

.product__thumb-btn img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
