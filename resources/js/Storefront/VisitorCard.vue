<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * The floating visitor counter.
 *
 * Five live figures: who is on the shop now, and the unique visitors today,
 * this week, this month and in total. It polls a cached endpoint — the numbers
 * are recomputed once a minute by the scheduled job, so a busy shop is cache
 * reads rather than queries no matter how many tabs are open.
 *
 * Three rules it follows, because a permanent floating panel that ignores them
 * is a nuisance rather than a feature:
 *  - It stops polling when the tab is not visible. An open tab in a background
 *    window is not a shopper, and it should not be asking for numbers.
 *  - It collapses to a pill, and remembers that choice for that browser.
 *  - It starts collapsed on a phone, where the screen belongs to the shop.
 */
const POLL_MS = 20000;
const STORAGE_KEY = 'shaniena:visitors:open';

const counts = ref(null);
const failed = ref(false);
const open = ref(true);
const bumped = ref(new Set());

let timer = null;
let controller = null;

const rows = computed(() => [
    { key: 'today', label: 'Today', value: counts.value?.today },
    { key: 'week', label: 'This week', value: counts.value?.week },
    { key: 'month', label: 'This month', value: counts.value?.month },
    { key: 'overall', label: 'All time', value: counts.value?.overall },
]);

const online = computed(() => counts.value?.online ?? 0);

const format = (value) => (value === undefined || value === null ? '—' : Number(value).toLocaleString('en-MY'));

async function poll() {
    controller?.abort();
    controller = new AbortController();

    try {
        const response = await fetch('/visitors', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const next = await response.json();

        // Flash only what actually changed, so a number moving is noticed and
        // four static ones are not.
        if (counts.value) {
            const changed = new Set(
                ['online', 'today', 'week', 'month', 'overall'].filter((k) => next[k] !== counts.value[k]),
            );

            if (changed.size) {
                bumped.value = changed;
                setTimeout(() => { bumped.value = new Set(); }, 900);
            }
        }

        counts.value = next;
        failed.value = false;
    } catch (error) {
        if (error.name !== 'AbortError') {
            // The shop works without this panel; it says so and keeps trying.
            failed.value = true;
        }
    }
}

function start() {
    stop();
    poll();
    timer = setInterval(poll, POLL_MS);
}

function stop() {
    clearInterval(timer);
    timer = null;
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
        start();
    } else {
        stop();
        controller?.abort();
    }
}

watch(open, (isOpen) => {
    try {
        localStorage.setItem(STORAGE_KEY, isOpen ? '1' : '0');
    } catch {
        // Private browsing, or storage switched off. The panel still works,
        // it just forgets between pages.
    }
});

onMounted(() => {
    let stored = null;

    try {
        stored = localStorage.getItem(STORAGE_KEY);
    } catch {
        stored = null;
    }

    open.value = stored === null
        ? window.matchMedia('(min-width: 768px)').matches
        : stored === '1';

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
    <aside class="visitor-card" :class="{ 'is-collapsed': !open }" aria-label="Visitor counts">
        <button type="button" class="visitor-card__toggle" :aria-expanded="open"
            :aria-label="open ? 'Hide visitor counts' : 'Show visitor counts'" @click="open = !open">
            <span class="visitor-card__dot" :class="{ 'is-live': online > 0 }"></span>

            <span class="visitor-card__now">
                <b :class="{ 'is-bumped': bumped.has('online') }">{{ format(online) }}</b>
                <span>online now</span>
            </span>

            <span class="visitor-card__chevron" aria-hidden="true">{{ open ? '⌄' : '⌃' }}</span>
        </button>

        <div v-show="open" class="visitor-card__body">
            <!-- Polite, not assertive: a screen reader should not be
                 interrupted mid-sentence because a counter ticked. -->
            <dl aria-live="polite">
                <div v-for="row in rows" :key="row.key" class="visitor-card__row">
                    <dt>{{ row.label }}</dt>
                    <dd :class="{ 'is-bumped': bumped.has(row.key) }">{{ format(row.value) }}</dd>
                </div>
            </dl>

            <p class="visitor-card__note">
                <template v-if="failed">Counts are unavailable right now.</template>
                <template v-else>Unique visitors</template>
            </p>
        </div>
    </aside>
</template>
