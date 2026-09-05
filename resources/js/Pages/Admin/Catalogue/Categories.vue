<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

const props = defineProps({
    categories: { type: Array, required: true },
    parents: { type: Array, required: true },
});

const editing = ref(null);
const form = useForm({ name: '', slug: '', parent_id: '', sort_order: 0, image: null });

const columns = [
    { key: 'name', label: 'Category' },
    { key: 'parent', label: 'Parent' },
    { key: 'products', label: 'Products', align: 'right' },
    { key: 'sort_order', label: 'Order', align: 'right' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function open(category = null) {
    editing.value = category ?? 'new';
    form.clearErrors();
    form.name = category?.name ?? '';
    form.slug = category?.slug ?? '';
    form.parent_id = category?.parent_id ?? '';
    form.sort_order = category?.sort_order ?? 0;
    form.image = null;
}

function submit() {
    const url = editing.value === 'new' ? '/admin/categories' : `/admin/categories/${editing.value.id}`;
    form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => { editing.value = null; } });
}

function remove(category) {
    if (!window.confirm(`Remove "${category.name}"? Products must be moved first.`)) return;
    router.delete(`/admin/categories/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Category Product" />

    <AdminLayout title="Category Product" current="category-product" :breadcrumb="[{ label: 'Manage Product' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="primary" size="sm" @click="open()">Add category</CButton>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable
                    :columns="columns"
                    :rows="categories"
                    min-width="40rem"
                    empty-title="No categories yet."
                    empty-body="Products need a category before they can go on sale."
                >
                    <template #cell:name="{ row }">
                        <div class="d-flex align-items-center gap-2">
                            <img v-if="row.image" :src="row.image" alt="" style="width:32px;height:32px;object-fit:cover" class="rounded border">
                            <span>
                                <span class="d-block">{{ row.name }}</span>
                                <span class="code small text-body-secondary">{{ row.slug }}</span>
                            </span>
                        </div>
                    </template>
                    <template #cell:parent="{ row }">
                        <span class="small">{{ row.parent ?? '—' }}</span>
                    </template>
                    <template #cell:products="{ row }"><span class="num">{{ row.products }}</span></template>
                    <template #cell:sort_order="{ row }"><span class="num">{{ row.sort_order }}</span></template>
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
            <CModalHeader><CModalTitle>{{ editing === 'new' ? 'Add category' : 'Edit category' }}</CModalTitle></CModalHeader>
            <CModalBody>
                <div class="mb-3">
                    <CFormLabel for="cname">Name</CFormLabel>
                    <CFormInput id="cname" v-model="form.name" :invalid="Boolean(form.errors.name)" />
                    <CFormFeedback v-if="form.errors.name" invalid>{{ form.errors.name }}</CFormFeedback>
                </div>
                <div class="mb-3">
                    <CFormLabel for="cslug">Slug</CFormLabel>
                    <CFormInput id="cslug" v-model="form.slug" class="code" :invalid="Boolean(form.errors.slug)" />
                    <CFormFeedback v-if="form.errors.slug" invalid>{{ form.errors.slug }}</CFormFeedback>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-8">
                        <CFormLabel for="cparent">Parent category</CFormLabel>
                        <CFormSelect id="cparent" v-model="form.parent_id" :invalid="Boolean(form.errors.parent_id)">
                            <option value="">None — top level</option>
                            <option v-for="p in parents" :key="p.id" :value="p.id"
                                :disabled="editing !== 'new' && p.id === editing.id">{{ p.name }}</option>
                        </CFormSelect>
                        <CFormFeedback v-if="form.errors.parent_id" invalid>{{ form.errors.parent_id }}</CFormFeedback>
                    </div>
                    <div class="col-4">
                        <CFormLabel for="csort">Order</CFormLabel>
                        <CFormInput id="csort" v-model="form.sort_order" type="number" min="0" />
                    </div>
                </div>
                <div>
                    <CFormLabel for="cimage">Image</CFormLabel>
                    <CFormInput id="cimage" type="file" accept="image/*" @change="form.image = $event.target.files[0] ?? null" />
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editing = null">Cancel</CButton>
                <CButton color="primary" :disabled="form.processing" @click="submit">
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
