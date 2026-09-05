<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';

const props = defineProps({ sliders: { type: Array, required: true } });

const list = ref([...props.sliders]);
watch(() => props.sliders, (value) => { list.value = [...value]; });

const adding = ref(false);
const form = useForm({ title: '', link_url: '', image: null });

function saveOrder() {
    router.post('/admin/sliders/reorder', { order: list.value.map((s) => s.id) }, { preserveScroll: true });
}

function submit() {
    form.post('/admin/sliders', { forceFormData: true, preserveScroll: true, onSuccess: () => { adding.value = false; form.reset(); } });
}

function toggle(slide) {
    router.put(`/admin/sliders/${slide.id}`, {
        title: slide.title, link_url: slide.link_url, status: !slide.status,
    }, { preserveScroll: true });
}

function remove(slide) {
    if (!window.confirm('Remove this slide?')) return;
    router.delete(`/admin/sliders/${slide.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Slider Setting" />

    <AdminLayout title="Slider Setting" current="slider-setting" :breadcrumb="[{ label: 'Settings' }]">
        <div class="d-flex justify-content-end mb-3">
            <CButton color="primary" size="sm" @click="adding = !adding">{{ adding ? 'Close' : 'Add slide' }}</CButton>
        </div>

        <CCard v-if="adding" class="border mb-3">
            <CCardBody class="row g-3">
                <div class="col-md-4">
                    <CFormLabel for="stitle">Title</CFormLabel>
                    <CFormInput id="stitle" v-model="form.title" />
                </div>
                <div class="col-md-4">
                    <CFormLabel for="slink">Link URL</CFormLabel>
                    <CFormInput id="slink" v-model="form.link_url" placeholder="https://…" :invalid="Boolean(form.errors.link_url)" />
                    <CFormFeedback v-if="form.errors.link_url" invalid>{{ form.errors.link_url }}</CFormFeedback>
                </div>
                <div class="col-md-4">
                    <CFormLabel for="simage">Image</CFormLabel>
                    <CFormInput id="simage" type="file" accept="image/*" :invalid="Boolean(form.errors.image)"
                        @change="form.image = $event.target.files[0] ?? null" />
                    <CFormFeedback v-if="form.errors.image" invalid>{{ form.errors.image }}</CFormFeedback>
                </div>
                <div class="col-12">
                    <CButton color="primary" :disabled="form.processing" @click="submit">{{ form.processing ? 'Uploading…' : 'Add slide' }}</CButton>
                </div>
            </CCardBody>
        </CCard>

        <CCard class="border">
            <CCardHeader class="bg-transparent d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Running order</span>
                <span class="small text-body-secondary">Drag to reorder</span>
            </CCardHeader>
            <CCardBody :class="list.length ? '' : 'p-0'">
                <EmptyState v-if="!list.length" title="No slides yet." body="The homepage carousel is empty until you add one." />

                <draggable v-else v-model="list" item-key="id" handle=".drag" class="d-flex flex-column gap-2" @end="saveOrder">
                    <template #item="{ element }">
                        <div class="d-flex align-items-center gap-3 border rounded p-2">
                            <span class="drag text-body-secondary" style="cursor:grab" aria-hidden="true">⠿</span>
                            <img :src="element.image" alt="" style="width:96px;height:54px;object-fit:cover" class="rounded border">
                            <span class="flex-grow-1">
                                <span class="d-block">{{ element.title || 'Untitled slide' }}</span>
                                <a v-if="element.link_url" :href="element.link_url" target="_blank" rel="noopener" class="small">{{ element.link_url }}</a>
                            </span>
                            <CBadge :color="element.status ? 'success' : 'secondary'" shape="rounded-pill">
                                {{ element.status ? 'Live' : 'Hidden' }}
                            </CBadge>
                            <CButton size="sm" color="secondary" variant="outline" @click="toggle(element)">
                                {{ element.status ? 'Hide' : 'Show' }}
                            </CButton>
                            <CButton size="sm" color="secondary" variant="ghost" @click="remove(element)">Remove</CButton>
                        </div>
                    </template>
                </draggable>
            </CCardBody>
        </CCard>
    </AdminLayout>
</template>
