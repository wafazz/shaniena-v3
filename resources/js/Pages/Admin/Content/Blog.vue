<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DataTable from '../../../Components/DataTable.vue';

defineProps({ posts: { type: Object, required: true } });

const editing = ref(null);
const form = useForm({ title: '', contents: '' });

const columns = [
    { key: 'title', label: 'Post' },
    { key: 'author', label: 'Author' },
    { key: 'readers', label: 'Readers', align: 'right' },
    { key: 'actions', label: '', headerClass: 'actions-col' },
];

function open(post = null) {
    editing.value = post ?? 'new';
    form.clearErrors();
    form.title = post?.title ?? '';
    form.contents = post?.contents ?? '';
}

function submit() {
    if (editing.value === 'new') {
        form.post('/admin/blog', { preserveScroll: true, onSuccess: () => { editing.value = null; } });
    } else {
        form.put(`/admin/blog/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
    }
}

function remove(post) {
    if (!window.confirm(`Remove "${post.title}"?`)) return;
    router.delete(`/admin/blog/${post.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Announcement & Blog" />

    <AdminLayout title="Announcement & Blog" current="announcement-blog" :breadcrumb="[{ label: 'Account' }]">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="small text-body-secondary mb-0">
                Announcements are not migrated — the <span class="code">announcement</span> table has no schema in the source.
            </p>
            <CButton color="primary" size="sm" @click="open()">Write a post</CButton>
        </div>

        <CCard class="border">
            <CCardBody class="p-0">
                <DataTable :columns="columns" :rows="posts.data" :meta="posts.meta" min-width="40rem"
                    empty-title="Nothing published yet." empty-body="Posts appear on the storefront blog as soon as they're saved.">
                    <template #cell:title="{ row }">
                        <div>{{ row.title }}</div>
                        <div class="small text-body-secondary nowrap">{{ row.published_at }}</div>
                    </template>
                    <template #cell:readers="{ row }"><span class="num">{{ row.readers }}</span></template>
                    <template #cell:actions="{ row }">
                        <div class="d-flex gap-1 justify-content-end">
                            <CButton size="sm" color="secondary" variant="outline" @click="open(row)">Edit</CButton>
                            <CButton size="sm" color="secondary" variant="ghost" @click="remove(row)">Remove</CButton>
                        </div>
                    </template>
                </DataTable>
            </CCardBody>
        </CCard>

        <CModal size="lg" :visible="Boolean(editing)" @close="editing = null" alignment="center">
            <CModalHeader><CModalTitle>{{ editing === 'new' ? 'New post' : 'Edit post' }}</CModalTitle></CModalHeader>
            <CModalBody>
                <div class="mb-3">
                    <CFormLabel for="ptitle">Title</CFormLabel>
                    <CFormInput id="ptitle" v-model="form.title" :invalid="Boolean(form.errors.title)" />
                    <CFormFeedback v-if="form.errors.title" invalid>{{ form.errors.title }}</CFormFeedback>
                </div>
                <div>
                    <CFormLabel for="pcontents">Content</CFormLabel>
                    <CFormTextarea id="pcontents" v-model="form.contents" rows="12" :invalid="Boolean(form.errors.contents)" />
                    <CFormFeedback v-if="form.errors.contents" invalid>{{ form.errors.contents }}</CFormFeedback>
                </div>
            </CModalBody>
            <CModalFooter>
                <CButton color="secondary" variant="outline" @click="editing = null">Cancel</CButton>
                <CButton color="primary" :disabled="form.processing" @click="submit">{{ form.processing ? 'Saving…' : 'Save post' }}</CButton>
            </CModalFooter>
        </CModal>
    </AdminLayout>
</template>
