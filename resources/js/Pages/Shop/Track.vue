<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Seo from '../../Storefront/Seo.vue';
import StorefrontLayout from '../../Layouts/StorefrontLayout.vue';

defineProps({
    searched: { type: Boolean, default: false },
    order: { type: Object, default: null },
});

const form = useForm({ order: '', email: '' });
</script>

<template>
    <Seo title="Track your order" description="Check where your order has got to." :index="false" />

    <StorefrontLayout>
        <div class="breadcrumb-option">
            <div class="container">
                <div class="breadcrumb__links"><Link href="/">Home</Link><span>Track your order</span></div>
            </div>
        </div>

        <section class="checkout spad">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-7">
                        <h4 class="mb-2">Track your order</h4>
                        <p class="mb-4">Enter the order number from your confirmation email, and the email address you ordered with.</p>

                        <!-- checkout__form / checkout__form__input, not
                             checkout__input: the latter styles nothing, which
                             is what left this form as bare browser boxes.
                             Same fix, and same reason, as Checkout.vue. -->
                        <form class="checkout__form"
                            @submit.prevent="form.get('/track-order', { preserveState: true })">
                            <div class="checkout__form__input">
                                <label for="track-order">Order number<span>*</span></label>
                                <input id="track-order" v-model="form.order" type="text"
                                    placeholder="#00000123" autocomplete="off" required>
                            </div>
                            <div class="checkout__form__input">
                                <label for="track-email">Email<span>*</span></label>
                                <input id="track-email" v-model="form.email" type="email"
                                    autocomplete="email" required>
                            </div>
                            <button type="submit" class="site-btn" :disabled="form.processing">
                                {{ form.processing ? 'Looking…' : 'Track' }}
                            </button>
                        </form>

                        <!-- role=alert so the result is announced, not just
                             painted: the visitor who needs this most is the
                             one who cannot see the page change. -->
                        <p v-if="searched && !order" class="track__miss" role="alert">
                            We couldn't find an order with that number and email together. Check both and try again.
                        </p>

                        <div v-if="order" class="track__result">
                            <div class="track__head">
                                <span class="track__ref">Order {{ order.reference }}</span>
                                <span class="track__placed">Placed {{ order.placed_at }}</span>
                            </div>

                            <p class="track__status" :class="{ 'is-offline': order.stage === null }">
                                {{ order.status }}
                            </p>

                            <ol v-if="order.stage !== null" class="track__steps" aria-label="Order progress">
                                <li v-for="(stage, i) in order.stages" :key="stage" class="track__step"
                                    :class="{ 'is-done': i < order.stage, 'is-now': i === order.stage }"
                                    :aria-current="i === order.stage ? 'step' : undefined">
                                    <span class="track__dot" aria-hidden="true"></span>
                                    <span class="track__step-label">{{ stage }}</span>
                                </li>
                            </ol>

                            <dl class="track__facts">
                                <div>
                                    <dt>Total</dt>
                                    <dd>{{ order.currency }} {{ order.total }}</dd>
                                </div>
                                <div v-if="order.courier">
                                    <dt>Courier</dt>
                                    <dd>{{ order.courier }}</dd>
                                </div>
                                <div v-if="order.awb">
                                    <dt>Consignment</dt>
                                    <dd>{{ order.awb }}</dd>
                                </div>
                            </dl>

                            <ul v-if="order.items.length" class="track__items">
                                <li v-for="(item, i) in order.items" :key="i">
                                    <span>{{ item.name }}</span>
                                    <span class="track__qty">×{{ item.quantity }}</span>
                                </li>
                            </ul>

                            <!-- noreferrer, not just noopener: this page's URL
                                 carries the customer's email in the query
                                 string, and noopener alone still hands it to
                                 the courier in the Referer header. -->
                            <a v-if="order.tracking_url" :href="order.tracking_url" target="_blank"
                                rel="noopener noreferrer" class="site-btn track__cta">
                                Track with {{ order.courier }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>

<style scoped>
/* Both themes set --shop-accent, so nothing here pins Ashion's red. */
.track__miss {
    margin-top: 1.5rem;
    padding: 0.9rem 1rem;
    border-left: 3px solid var(--shop-accent, #ca1515);
    background: #fbf6f6;
    color: #444;
}

.track__result {
    margin-top: 2rem;
    border: 1px solid #ebebeb;
    padding: 1.5rem;
}

.track__head {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem;
    color: #7a7a7a;
    font-size: 0.85rem;
}

.track__ref {
    font-weight: 600;
    color: #444;
}

/* The one thing the visitor came for, so it is the largest thing here. */
.track__status {
    margin: 0.35rem 0 1.5rem;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--shop-accent, #ca1515);
}

.track__status.is-offline {
    color: #444;
}

.track__steps {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    list-style: none;
    margin: 0 0 1.75rem;
    padding: 0;
}

.track__step {
    position: relative;
    padding-top: 1.4rem;
    text-align: center;
    font-size: 0.78rem;
    color: #9a9a9a;
}

/* The rail leads INTO each step, so it fills only once that step is reached.
   Drawn out of it instead, the current stage shows a half-filled line running
   towards a stage the order has not got to yet. */
.track__step::before {
    content: '';
    position: absolute;
    top: 0.32rem;
    /* Between the two dots, not across them: the dot sits at 50% of its own
       cell, so the previous one is at -50% of this cell — then inset by the
       dot's radius at each end, or the rail paints over the dot and notches
       it. */
    left: calc(-50% + 0.375rem);
    width: calc(100% - 0.75rem);
    height: 2px;
    background: #ebebeb;
}

.track__step:first-child::before { display: none; }

.track__step.is-done::before,
.track__step.is-now::before { background: var(--shop-accent, #ca1515); }

.track__dot {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    background: #ebebeb;
}

.track__step.is-done .track__dot,
.track__step.is-now .track__dot { background: var(--shop-accent, #ca1515); }

.track__step.is-now .track__dot {
    box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.06);
}

.track__step.is-now .track__step-label {
    color: #444;
    font-weight: 600;
}

.track__facts {
    display: flex;
    flex-wrap: wrap;
    gap: 1.75rem;
    margin: 0;
}

.track__facts dt {
    font-size: 0.75rem;
    font-weight: 400;
    color: #9a9a9a;
    margin-bottom: 0.15rem;
}

.track__facts dd {
    margin: 0;
    font-weight: 600;
    color: #444;
}

/* The rule belongs to the list, not to the facts above it: a cancelled order
   has no active lines, and a border with nothing under it reads as a bug. */
.track__items {
    list-style: none;
    margin: 1.25rem 0 0;
    padding: 1.25rem 0 0;
    border-top: 1px solid #ebebeb;
}

.track__items li {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.35rem 0;
    font-size: 0.9rem;
    color: #444;
}

.track__qty {
    color: #9a9a9a;
    white-space: nowrap;
}

.track__cta {
    margin-top: 1.5rem;
}

@media (max-width: 575px) {
    .track__steps {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }

    /* The rail only reads as a rail horizontally; stacked, it is noise. */
    .track__step {
        padding: 0 0 0 1.5rem;
        text-align: left;
    }

    .track__step::before { display: none; }

    .track__dot {
        top: 0.2rem;
        left: 0;
        transform: none;
    }
}
</style>
