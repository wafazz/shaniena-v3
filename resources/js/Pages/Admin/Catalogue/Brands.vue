<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

defineProps({ brands: { type: Array, required: true } });

const editing = ref(null);
const form = useForm({ name: '', slug: '', image: null });

const columns = [
    { key: 'name', label: 'Brand' },
    { key: 'products', label: 'Products', align: 'right' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function open(brand = null) {
    editing.value = brand ?? 'new';
    form.clearErrors();
    form.name = brand?.name ?? '';
    form.slug = brand?.slug ?? '';
    form.image = null;
}

function submit() {
    const url = editing.value === 'new' ? '/admin/brands' : `/admin/brands/${editing.value.id}`;
    form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => { editing.value = null; } });
}

function remove(brand) {
    if (!window.confirm(`Remove "${brand.name}"? Products must be moved first.`)) return;
    router.delete(`/admin/brands/${brand.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Brand Product" />

    <AdminLayout title="Brand Product" current="brand-product" :breadcrumb="[{ label: 'Manage Product' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="primary" size="sm" @click="open()">Add brand</CButton>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="brands" min-width="34rem"
                    empty-title="No brands yet." empty-body="Brands are optional, but they drive the storefront's brand pages.">
                    <template #cell:name="{ row }">
                        <div class="d-flex align-items-center gap-2">
                            <img v-if="row.image" :src="row.image" alt="" style="width:32px;height:32px;object-fit:cover" class="rounded border">
                            <span>
                                <span class="d-block">{{ row.name }}</span>
                                <span class="code small text-body-secondary">{{ row.slug }}</span>
                            </span>
                        </div>
                    </template>
                    <template #cell:products="{ row }"><span class="num">{{ row.products }}</span></template>
                    <template #cell:actions="{ row }">
                        <div class="d-flex gap-1 justify-content-end">
                            <CButton size="sm" color="secondary" variant="outline" @click="open(row)">Edit</CButton>
                            <CButton size="sm" color="secondary" variant="ghost" :disabled="row.products > 0"
                                :title="row.products > 0 ? 'Move its products first' : ''" @click="remove(row)">Remove</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>

        <CModal :visible="Boolean(editing)" @close="editing = null" alignment="center">
            <CModalHeader><CModalTitle>{{ editing === 'new' ? 'Add brand' : 'Edit brand' }}</CModalTitle></CModalHeader>
            <CModalBody>
                <div class="mb-3">
                    <CFormLabel for="bname">Name</CFormLabel>
                    <CFormInput id="bname" v-model="form.name" :invalid="Boolean(form.errors.name)" />
                    <CFormFeedback v-if="form.errors.name" invalid>{{ form.errors.name }}</CFormFeedback>
                </div>
                <div class="mb-3">
                    <CFormLabel for="bslug">Slug</CFormLabel>
                    <CFormInput id="bslug" v-model="form.slug" class="code" :invalid="Boolean(form.errors.slug)" />
                    <CFormFeedback v-if="form.errors.slug" invalid>{{ form.errors.slug }}</CFormFeedback>
                </div>
                <div>
                    <CFormLabel for="bimage">Logo</CFormLabel>
                    <CFormInput id="bimage" type="file" accept="image/*" @change="form.image = $event.target.files[0] ?? null" />
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editing = null">Cancel</CButton>
                <CButton color="primary" :disabled="form.processing" @click="submit">{{ form.processing ? 'Saving…' : 'Save' }}</CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
