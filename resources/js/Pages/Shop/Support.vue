<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

const props = defineProps({
    searched: { type: Boolean, default: false },
    prefill: { type: Object, default: () => ({}) },
    ticket: { type: Object, default: null },
    member: { type: Object, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const tab = ref(props.searched || props.ticket || props.prefill?.ticket_no ? 'find' : 'new');

const open = useForm({
    customer_name: props.member?.name ?? '',
    customer_email: props.member?.email ?? '',
    order_id: '',
    title: '',
    description: '',
    priority: 'medium',
});

const find = useForm({ ticket_no: props.prefill?.ticket_no ?? '', email: props.member?.email ?? '' });
const reply = useForm({ ticket_no: '', email: '', message: '' });
</script>

<template>
    <Head title="Support" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>Support</span></div>
            </div>
        </div>

        <section class="checkout spad">
            <div class="container">
                <p v-if="flash.success" class="text-success">{{ flash.success }}</p>

                <div class="d-flex gap-3 mb-4">
                    <button type="button" class="btn" :class="tab === 'new' ? 'btn-dark' : 'btn-outline-dark'"
                        @click="tab = 'new'">Open a ticket</button>
                    <button type="button" class="btn" :class="tab === 'find' ? 'btn-dark' : 'btn-outline-dark'"
                        @click="tab = 'find'">Check an existing one</button>
                </div>

                <div v-if="tab === 'new'" class="row">
                    <div class="col-lg-7">
                        <form @submit.prevent="open.post('/support')">
                            <div class="row">
                                <div class="col-md-6 checkout__input">
                                    <p>Your name<span>*</span></p>
                                    <input v-model="open.customer_name" type="text" required>
                                </div>
                                <div class="col-md-6 checkout__input">
                                    <p>Email<span>*</span></p>
                                    <input v-model="open.customer_email" type="email" required>
                                </div>
                            </div>
                            <div class="checkout__input">
                                <p>Order number <span class="text-muted">(if it's about an order)</span></p>
                                <input v-model="open.order_id" type="text" placeholder="#00000123">
                            </div>
                            <div class="checkout__input">
                                <p>Subject<span>*</span></p>
                                <input v-model="open.title" type="text" required>
                            </div>
                            <div class="checkout__input">
                                <p>How can we help?<span>*</span></p>
                                <textarea v-model="open.description" rows="6" required></textarea>
                                <small v-if="open.errors.description" class="text-danger">{{ open.errors.description }}</small>
                            </div>
                            <div class="checkout__input">
                                <p>How urgent is it?</p>
                                <select v-model="open.priority" class="form-select">
                                    <option value="low">Not urgent</option>
                                    <option value="medium">Normal</option>
                                    <option value="high">Urgent</option>
                                </select>
                            </div>
                            <button type="submit" class="site-btn" :disabled="open.processing">
                                {{ open.processing ? 'Sending…' : 'Open ticket' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div v-else class="row">
                    <div class="col-lg-5">
                        <form @submit.prevent="find.get('/support', { preserveState: true })">
                            <div class="checkout__input">
                                <p>Ticket number<span>*</span></p>
                                <input v-model="find.ticket_no" type="text" placeholder="T-XXXXXXXX" required>
                            </div>
                            <div class="checkout__input">
                                <p>Email<span>*</span></p>
                                <input v-model="find.email" type="email" required>
                            </div>
                            <button type="submit" class="site-btn" :disabled="find.processing">Find ticket</button>
                        </form>

                        <p v-if="searched && !ticket" class="text-danger mt-3">
                            No ticket matches that number and email together.
                        </p>
                    </div>

                    <div v-if="ticket" class="col-lg-7">
                        <div class="border p-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <b>{{ ticket.ticket_no }}</b>
                                <span class="small text-uppercase">{{ ticket.status }}</span>
                            </div>
                            <h6>{{ ticket.title }}</h6>
                            <p style="white-space: pre-wrap">{{ ticket.description }}</p>

                            <div v-for="message in ticket.replies" :key="message.id" class="border-top pt-2 mt-2">
                                <div class="small text-muted d-flex justify-content-between">
                                    <b>{{ message.from_staff ? 'Support' : 'You' }}</b>
                                    <span>{{ message.at }}</span>
                                </div>
                                <p class="mb-0" style="white-space: pre-wrap">{{ message.message }}</p>
                            </div>

                            <form v-if="!ticket.closed" class="mt-3"
                                @submit.prevent="reply.ticket_no = ticket.ticket_no; reply.email = find.email; reply.post('/support/reply', { preserveScroll: true, onSuccess: () => reply.reset('message') })">
                                <textarea v-model="reply.message" rows="3" class="form-control mb-2"
                                    placeholder="Add to this ticket…" required></textarea>
                                <button type="submit" class="site-btn" :disabled="reply.processing">Send reply</button>
                            </form>
                            <p v-else class="small text-muted mt-3 mb-0">This ticket is closed. Open a new one if you still need help.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>
