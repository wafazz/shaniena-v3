<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    replies: { type: Array, required: true },
    attachments: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const form = useForm({ message: '', status: props.ticket.status });
const label = (value) => String(value ?? '').replace(/_/g, ' ');

function submit() {
    form.post(`/admin/support/tickets/${props.ticket.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
}
</script>

<template>
    <Head :title="ticket.ticket_no ?? 'Ticket'" />

    <AdminLayout :title="ticket.title ?? 'Support ticket'" current="support/tickets"
        :breadcrumb="[{ label: 'Support' }, { label: 'Support Tickets' }]">
        <div class="mb-3"><Link href="/admin/support/tickets" class="small">← Back to tickets</Link></div>

        <CRow class="g-3 align-items-start">
            <CCol :lg="8">
                <CCard class="border mb-3">
                    <CCardHeader class="bg-transparent d-flex justify-content-between align-items-center">
                        <span class="code">{{ ticket.ticket_no ?? '—' }}</span>
                        <CBadge color="secondary" shape="rounded-pill" class="text-uppercase">{{ label(ticket.status) }}</CBadge>
                    </CCardHeader>
                    <CCardBody>
                        <p class="mb-0" style="white-space: pre-wrap">{{ ticket.description }}</p>
                        <div v-if="attachments.length" class="mt-3 d-flex flex-wrap gap-2">
                            <a v-for="file in attachments" :key="file.id" :href="file.url" target="_blank" rel="noopener"
                                class="small border rounded px-2 py-1">{{ file.filename }}</a>
                        </div>
                    </CCardBody>
                </CCard>

                <CCard class="border mb-3">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Conversation</span></CCardHeader>
                    <CCardBody>
                        <p v-if="!replies.length" class="small text-body-secondary mb-0">Nobody has replied yet.</p>
                        <div v-for="reply in replies" :key="reply.id" class="pb-3 mb-3 border-bottom">
                            <div class="d-flex justify-content-between small text-body-secondary mb-1">
                                <span class="fw-semibold" :class="reply.from_staff ? 'text-body' : ''">
                                    {{ reply.from_staff ? 'Support' : ticket.customer }}
                                </span>
                                <span class="nowrap">{{ reply.at }}</span>
                            </div>
                            <p class="mb-0" style="white-space: pre-wrap">{{ reply.message }}</p>
                        </div>
                    </CCardBody>
                </CCard>

                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Reply</span></CCardHeader>
                    <CCardBody>
                        <form @submit.prevent="submit">
                            <CFormTextarea v-model="form.message" rows="5" placeholder="Write your reply…"
                                :invalid="Boolean(form.errors.message)" class="mb-3" />
                            <CFormFeedback v-if="form.errors.message" invalid class="d-block mb-2">{{ form.errors.message }}</CFormFeedback>
                            <div class="d-flex align-items-center gap-3">
                                <CButton type="submit" color="primary" :disabled="form.processing">
                                    {{ form.processing ? 'Sending…' : 'Send reply' }}
                                </CButton>
                                <CFormSelect v-model="form.status" style="max-width: 15rem" size="sm">
                                    <option v-for="status in statuses" :key="status" :value="status">
                                        Set to {{ label(status) }}
                                    </option>
                                </CFormSelect>
                            </div>
                        </form>
                    </CCardBody>
                </CCard>
            </CCol>

            <CCol :lg="4">
                <CCard class="border">
                    <CCardHeader class="bg-transparent"><span class="fw-semibold">Customer</span></CCardHeader>
                    <CCardBody>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-body-secondary fw-normal">Name</dt><dd class="col-7">{{ ticket.customer }}</dd>
                            <dt class="col-5 text-body-secondary fw-normal">Email</dt><dd class="col-7">{{ ticket.email }}</dd>
                            <dt class="col-5 text-body-secondary fw-normal">Order</dt><dd class="col-7">{{ ticket.order_id ?? '—' }}</dd>
                            <dt class="col-5 text-body-secondary fw-normal">Priority</dt><dd class="col-7 text-uppercase">{{ ticket.priority }}</dd>
                            <dt class="col-5 text-body-secondary fw-normal">Opened</dt><dd class="col-7">{{ ticket.opened_at }}</dd>
                        </dl>
                    </CCardBody>
                </CCard>
            </CCol>
        </CRow>
    </AdminLayout>
</template>
