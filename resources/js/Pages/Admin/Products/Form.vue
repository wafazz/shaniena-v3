<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    countries: { type: Array, required: true },
});

const isEdit = computed(() => Boolean(props.product));

const blankVariant = () => ({ id: null, variant_name: '', sku: '', price_retail: '', price_sale: '', max_purchase: 5 });

const form = useForm({
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    description: props.product?.description ?? '',
    category_id: props.product?.category_id ?? '',
    brand_id: props.product?.brand_id ?? '',
    weight: props.product?.weight ?? '',
    length: props.product?.length ?? '',
    width: props.product?.width ?? '',
    height: props.product?.height ?? '',
    type: props.product?.type ?? 'simple',
    price_capital: props.product?.price_capital ?? '',
    status: props.product?.status ?? true,
    variants: props.product?.variants?.length ? [...props.product.variants] : [blankVariant()],
    prices: Object.fromEntries(props.countries.map((c) => [
        c.id,
        props.product?.prices?.[c.id] ?? { market_price: '', sale_price: '' },
    ])),
    images: [],
    keep_images: props.product?.images?.map((i) => i.id) ?? [],
});

// Slug follows the name until someone edits it themselves.
const slugTouched = ref(isEdit.value);
watch(() => form.name, (name) => {
    if (!slugTouched.value) {
        form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});

const existingImages = computed(() => (props.product?.images ?? []).filter((i) => form.keep_images.includes(i.id)));
const imageCount = computed(() => existingImages.value.length + form.images.length);

function addVariant() { form.variants.push(blankVariant()); }
function removeVariant(index) {
    if (form.variants.length > 1) form.variants.splice(index, 1);
}
function dropImage(id) { form.keep_images = form.keep_images.filter((i) => i !== id); }
function onFiles(event) { form.images = Array.from(event.target.files ?? []); }

function submit() {
    const options = { preserveScroll: true, forceFormData: true };
    if (isEdit.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(`/admin/products/${props.product.id}`, options);
    } else {
        form.post('/admin/products', options);
    }
}

// A long form with no guard loses work.
const dirty = computed(() => form.isDirty);
</script>

<template>
    <Head :title="isEdit ? 'Update Product' : 'New Product'" />

    <AdminLayout
        :title="isEdit ? 'Update Product' : 'New Product'"
        current="new-product"
        :breadcrumb="[{ label: 'Manage Product' }]"
    >
        <form @submit.prevent="submit">
            <CRow class="g-3">
                <CCol :lg="8">
                    <CCard class="border mb-3">
                        <CCardHeader class="bg-transparent"><span class="fw-semibold">Identity</span></CCardHeader>
                        <CCardBody class="row g-3">
                            <div class="col-12">
                                <CFormLabel for="name">Product name</CFormLabel>
                                <CFormInput id="name" v-model="form.name" :invalid="Boolean(form.errors.name)" />
                                <CFormFeedback v-if="form.errors.name" invalid>{{ form.errors.name }}</CFormFeedback>
                            </div>
                            <div class="col-12">
                                <CFormLabel for="slug">Slug</CFormLabel>
                                <CFormInput id="slug" v-model="form.slug" class="code" :invalid="Boolean(form.errors.slug)" @input="slugTouched = true" />
                                <CFormFeedback v-if="form.errors.slug" invalid>{{ form.errors.slug }}</CFormFeedback>
                            </div>
                            <div class="col-md-6">
                                <CFormLabel for="category_id">Category</CFormLabel>
                                <CFormSelect id="category_id" v-model="form.category_id" :invalid="Boolean(form.errors.category_id)">
                                    <option value="">Choose category</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </CFormSelect>
                                <CFormFeedback v-if="form.errors.category_id" invalid>{{ form.errors.category_id }}</CFormFeedback>
                            </div>
                            <div class="col-md-6">
                                <CFormLabel for="brand_id">Brand</CFormLabel>
                                <CFormSelect id="brand_id" v-model="form.brand_id">
                                    <option value="">Choose brand</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </CFormSelect>
                            </div>
                            <div class="col-12">
                                <CFormLabel for="description">Description</CFormLabel>
                                <CFormTextarea id="description" v-model="form.description" rows="6" />
                                <div class="form-text">Rich-text editing arrives with the shared RichText component.</div>
                            </div>
                        </CCardBody>
                    </CCard>

                    <CCard class="border mb-3">
                        <CCardHeader class="bg-transparent d-flex align-items-center justify-content-between">
                            <span class="fw-semibold">Type &amp; variants</span>
                            <CButton v-if="form.type === 'variable'" size="sm" color="secondary" variant="outline" @click="addVariant">Add variant</CButton>
                        </CCardHeader>
                        <CCardBody>
                            <div class="mb-3 d-flex gap-4">
                                <CFormCheck type="radio" name="type" id="simple" label="Simple" :model-value="form.type === 'simple'" @change="form.type = 'simple'" />
                                <CFormCheck type="radio" name="type" id="variable" label="Variable" :model-value="form.type === 'variable'" @change="form.type = 'variable'" />
                            </div>

                            <div v-for="(variant, index) in form.variants" :key="index" class="row g-2 align-items-end pb-3 mb-3 border-bottom">
                                <div v-if="form.type === 'variable'" class="col-md-4">
                                    <CFormLabel :for="`vn-${index}`" class="small mb-1">Variant name</CFormLabel>
                                    <CFormInput :id="`vn-${index}`" v-model="variant.variant_name" size="sm" placeholder="e.g. 50ml, Red XL"
                                        :invalid="Boolean(form.errors[`variants.${index}.variant_name`])" />
                                    <CFormFeedback v-if="form.errors[`variants.${index}.variant_name`]" invalid>
                                        {{ form.errors[`variants.${index}.variant_name`] }}
                                    </CFormFeedback>
                                </div>
                                <div class="col-md-3">
                                    <CFormLabel :for="`sku-${index}`" class="small mb-1">SKU</CFormLabel>
                                    <CFormInput :id="`sku-${index}`" v-model="variant.sku" size="sm" class="code"
                                        :invalid="Boolean(form.errors[`variants.${index}.sku`])" />
                                </div>
                                <div class="col-md-2">
                                    <CFormLabel :for="`pr-${index}`" class="small mb-1">Retail (RM)</CFormLabel>
                                    <CFormInput :id="`pr-${index}`" v-model="variant.price_retail" type="number" step="0.01" min="0" size="sm" />
                                </div>
                                <div class="col-md-2">
                                    <CFormLabel :for="`ps-${index}`" class="small mb-1">Sale (RM)</CFormLabel>
                                    <CFormInput :id="`ps-${index}`" v-model="variant.price_sale" type="number" step="0.01" min="0" size="sm" />
                                </div>
                                <div class="col-md-1 d-flex">
                                    <CButton v-if="form.type === 'variable' && form.variants.length > 1" size="sm" color="secondary" variant="ghost"
                                        :aria-label="`Remove variant ${index + 1}`" @click="removeVariant(index)">−</CButton>
                                </div>
                            </div>
                        </CCardBody>
                    </CCard>

                    <CCard class="border">
                        <CCardHeader class="bg-transparent"><span class="fw-semibold">Country pricing</span></CCardHeader>
                        <CCardBody>
                            <!-- Prices come from list_country_product_price, not
                                 the variant columns — decision 11 in the plan. -->
                            <p class="small text-body-secondary">These are the prices customers actually see. Market price shows struck through only when the sale price is lower.</p>
                            <div v-for="country in countries" :key="country.id" class="row g-2 align-items-end mb-2">
                                <div class="col-md-4"><span class="small fw-semibold">{{ country.name }}</span>
                                    <span class="small text-body-secondary ms-1">{{ country.sign }}</span></div>
                                <div class="col-md-4">
                                    <CFormLabel :for="`mp-${country.id}`" class="small mb-1">Market price</CFormLabel>
                                    <CFormInput :id="`mp-${country.id}`" v-model="form.prices[country.id].market_price" type="number" step="0.01" min="0" size="sm" />
                                </div>
                                <div class="col-md-4">
                                    <CFormLabel :for="`sp-${country.id}`" class="small mb-1">Sale price</CFormLabel>
                                    <CFormInput :id="`sp-${country.id}`" v-model="form.prices[country.id].sale_price" type="number" step="0.01" min="0" size="sm" />
                                </div>
                            </div>
                            <p v-if="!countries.length" class="small text-body-secondary mb-0">No selling countries are active yet.</p>
                        </CCardBody>
                    </CCard>
                </CCol>

                <CCol :lg="4">
                    <CCard class="border mb-3">
                        <CCardHeader class="bg-transparent"><span class="fw-semibold">Dimensions</span></CCardHeader>
                        <CCardBody class="row g-3">
                            <div class="col-6">
                                <CFormLabel for="weight">Weight (g)</CFormLabel>
                                <CFormInput id="weight" v-model="form.weight" type="number" min="1" step="1" :invalid="Boolean(form.errors.weight)" />
                                <CFormFeedback v-if="form.errors.weight" invalid>{{ form.errors.weight }}</CFormFeedback>
                            </div>
                            <div class="col-6">
                                <CFormLabel for="length">Length (mm)</CFormLabel>
                                <CFormInput id="length" v-model="form.length" type="number" min="1" step="1" />
                            </div>
                            <div class="col-6">
                                <CFormLabel for="width">Width (mm)</CFormLabel>
                                <CFormInput id="width" v-model="form.width" type="number" min="1" step="1" />
                            </div>
                            <div class="col-6">
                                <CFormLabel for="height">Height (mm)</CFormLabel>
                                <CFormInput id="height" v-model="form.height" type="number" min="1" step="1" />
                            </div>
                            <div class="col-12">
                                <CFormLabel for="price_capital">Capital price (RM)</CFormLabel>
                                <CFormInput id="price_capital" v-model="form.price_capital" type="number" step="0.01" min="0.01" :invalid="Boolean(form.errors.price_capital)" />
                                <CFormFeedback v-if="form.errors.price_capital" invalid>{{ form.errors.price_capital }}</CFormFeedback>
                            </div>
                            <div class="col-12">
                                <CFormCheck id="status" v-model="form.status" label="On sale in the storefront" />
                            </div>
                        </CCardBody>
                    </CCard>

                    <CCard class="border">
                        <CCardHeader class="bg-transparent"><span class="fw-semibold">Images</span></CCardHeader>
                        <CCardBody>
                            <div v-if="existingImages.length" class="d-flex flex-wrap gap-2 mb-3">
                                <div v-for="image in existingImages" :key="image.id" class="position-relative">
                                    <img :src="image.url" alt="" style="width:72px;height:72px;object-fit:cover" class="rounded border">
                                    <CButton size="sm" color="secondary" variant="ghost" class="position-absolute top-0 end-0 p-0 px-1"
                                        :aria-label="`Remove image ${image.id}`" @click="dropImage(image.id)">×</CButton>
                                </div>
                            </div>
                            <CFormInput type="file" multiple accept="image/*" :invalid="Boolean(form.errors.images)" @change="onFiles" />
                            <div class="form-text">Up to 5 per product — <span class="num">{{ imageCount }}</span> selected.</div>
                            <CFormFeedback v-if="form.errors.images" invalid>{{ form.errors.images }}</CFormFeedback>
                        </CCardBody>
                    </CCard>
                </CCol>
            </CRow>

            <div class="d-flex align-items-center gap-3 mt-3">
                <CButton type="submit" color="primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save product' }}
                </CButton>
                <Link href="/admin/stock-control" class="small">Cancel</Link>
                <span v-if="dirty" class="small text-body-secondary">Unsaved changes</span>
            </div>
        </form>
    </AdminLayout>
</template>
