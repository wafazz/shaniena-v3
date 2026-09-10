<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
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

const find = useForm({
    ticket_no: props.prefill?.ticket_no ?? '',
    // The address in the link wins over the signed-in one: someone may be
    // reading a ticket they raised before they had an account.
    email: props.prefill?.email || props.member?.email || '',
});
const reply = useForm({ ticket_no: '', email: '', message: '' });

// The thread the page is showing. Starts as whatever was rendered and is then
// kept up to date by polling, so a staff reply arrives without the customer
// having to look the ticket up a second time to find out.
const thread = ref(props.ticket);
const arrived = ref(0);

// Same cadence as the visitor counter, for the same reason: often enough to
// feel live, rare enough that a tab left open all afternoon is 3 requests a
// minute.
const POLL_MS = 20000;

let timer = null;
let controller = null;

// The lookup this page was answered with. The poll has to send the same pair —
// there is no session behind a ticket, which is what stops a number on its own
// opening someone else's conversation.
const credentials = () => ({
    ticket_no: thread.value?.ticket_no ?? '',
    email: find.email,
});

async function poll() {
    const { ticket_no: ticketNo, email } = credentials();

    if (!ticketNo || !email) {
        return;
    }

    controller?.abort();
    controller = new AbortController();

    try {
        const response = await fetch(`/support/thread?${new URLSearchParams({ ticket_no: ticketNo, email })}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const next = await response.json();
        const before = thread.value?.replies?.length ?? 0;

        thread.value = next;

        // Only staff replies are news — the customer's own turn up the moment
        // they send them.
        if (next.replies.length > before && next.replies.at(-1)?.from_staff) {
            arrived.value = next.replies.length - before;
        }

        if (next.closed) {
            stop();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            // The page is perfectly usable without this; it just stops being
            // live. Nothing is shown, because nothing is wrong for the reader.
            stop();
        }
    }
}

function start() {
    stop();

    if (thread.value && !thread.value.closed) {
        timer = setInterval(poll, POLL_MS);
    }
}

function stop() {
    clearInterval(timer);
    timer = null;
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
        start();
        poll();
    } else {
        stop();
        controller?.abort();
    }
}

// A new lookup, or a reply that came back through Inertia, replaces the thread
// wholesale — otherwise the poll would keep refreshing the previous ticket.
watch(() => props.ticket, (ticket) => {
    thread.value = ticket;
    arrived.value = 0;
    start();
});

onMounted(() => {
    start();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    stop();
    controller?.abort();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <Seo title="Support" description="Open a ticket or pick up an existing one." :index="false" />

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
                        <form class="checkout__form" @submit.prevent="open.post('/support')">
                            <div class="row">
                                <div class="col-md-6 checkout__form__input">
                                    <label for="open-customer-name">Your name<span>*</span></label>
                                    <input id="open-customer-name" v-model="open.customer_name" type="text" required>
                                </div>
                                <div class="col-md-6 checkout__form__input">
                                    <label for="open-customer-email">Email<span>*</span></label>
                                    <input id="open-customer-email" v-model="open.customer_email" type="email" required>
                                </div>
                            </div>
                            <div class="checkout__form__input">
                                <label for="open-order-id">Order number <span class="text-muted">(if it's about an order)</span></label>
                                <input id="open-order-id" v-model="open.order_id" type="text" placeholder="#00000123">
                            </div>
                            <div class="checkout__form__input">
                                <label for="open-title">Subject<span>*</span></label>
                                <input id="open-title" v-model="open.title" type="text" required>
                            </div>
                            <div class="checkout__form__input">
                                <label for="open-description">How can we help?<span>*</span></label>
                                <textarea id="open-description" v-model="open.description" rows="6" required></textarea>
                                <small v-if="open.errors.description" class="text-danger">{{ open.errors.description }}</small>
                            </div>
                            <div class="checkout__form__input">
                                <label for="open-priority">How urgent is it?</label>
                                <select id="open-priority" v-model="open.priority" class="form-select">
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
                        <form class="checkout__form" @submit.prevent="find.get('/support', { preserveState: true })">
                            <div class="checkout__form__input">
                                <label for="find-ticket-no">Ticket number<span>*</span></label>
                                <input id="find-ticket-no" v-model="find.ticket_no" type="text" placeholder="T-XXXXXXXX" required>
                            </div>
                            <div class="checkout__form__input">
                                <label for="find-email">Email<span>*</span></label>
                                <input id="find-email" v-model="find.email" type="email" required>
                            </div>
                            <button type="submit" class="site-btn" :disabled="find.processing">Find ticket</button>
                        </form>

                        <p v-if="searched && !thread" class="text-danger mt-3">
                            No ticket matches that number and email together.
                        </p>
                    </div>

                    <div v-if="thread" class="col-lg-7">
                        <div class="border p-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-2">
                                <b>{{ thread.ticket_no }}</b>
                                <span class="small text-uppercase">{{ thread.status }}</span>
                            </div>
                            <h6>{{ thread.title }}</h6>
                            <p style="white-space: pre-wrap">{{ thread.description }}</p>

                            <p v-if="arrived" class="support__arrived" role="status">
                                {{ arrived === 1 ? 'Support replied.' : `${arrived} new replies.` }}
                            </p>

                            <div v-for="message in thread.replies" :key="message.id" class="border-top pt-2 mt-2">
                                <div class="small text-muted d-flex justify-content-between">
                                    <b>{{ message.from_staff ? 'Support' : 'You' }}</b>
                                    <span>{{ message.at }}</span>
                                </div>
                                <p class="mb-0" style="white-space: pre-wrap">{{ message.message }}</p>
                            </div>

                            <form v-if="!thread.closed" class="mt-3"
                                @submit.prevent="reply.ticket_no = thread.ticket_no; reply.email = find.email; reply.post('/support/reply', { preserveScroll: true, onSuccess: () => reply.reset('message') })">
                                <textarea v-model="reply.message" rows="3" class="form-control mb-2"
                                    aria-label="Your reply" placeholder="Add to this ticket…" required></textarea>
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

<style scoped>
.support__arrived {
    margin: 1rem 0 0;
    padding: 0.5rem 0.75rem;
    border-left: 3px solid var(--shop-accent, #ca1515);
    background: #f7f7f7;
    font-size: 0.85rem;
    color: #444;
}
</style>
