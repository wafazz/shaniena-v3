<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * "Add to home screen".
 *
 * Chrome will only offer this once it has fired beforeinstallprompt, which it
 * does when the manifest, icons and service worker are all in order — so the
 * bar appearing at all is itself the signal that the PWA is installable.
 */
const STORAGE_KEY = 'shaniena:install-dismissed';

const visible = ref(false);
let deferred = null;

// The bar is fixed across the bottom of the screen, where the visitor card
// also lives on a phone. The card steps aside for it rather than the two
// stacking on top of each other.
watch(visible, (showing) => {
    document.body.classList.toggle('has-install-banner', showing);
});

function dismissedBefore() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        // Private mode, or site data blocked. Offer it; the worst case is a
        // bar someone closes again.
        return false;
    }
}

function remember() {
    try {
        localStorage.setItem(STORAGE_KEY, '1');
    } catch {
        // Nothing to do — the bar simply reappears next visit.
    }
}

function onPrompt(event) {
    // Hold on to it: without preventDefault the browser shows its own bar and
    // the event cannot be replayed from a button later.
    event.preventDefault();
    deferred = event;

    if (! dismissedBefore()) visible.value = true;
}

function onInstalled() {
    visible.value = false;
    deferred = null;
    remember();
}

async function install() {
    if (! deferred) return;

    visible.value = false;
    deferred.prompt();
    await deferred.userChoice;

    // The event is single-use whichever way they answered.
    deferred = null;
}

function dismiss() {
    visible.value = false;
    remember();
}

onMounted(() => {
    // Already installed: standalone display, or iOS's own flag.
    const standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (standalone) return;

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);
});

onBeforeUnmount(() => {
    document.body.classList.remove('has-install-banner');
    window.removeEventListener('beforeinstallprompt', onPrompt);
    window.removeEventListener('appinstalled', onInstalled);
});
</script>

<template>
    <div v-if="visible" class="install-banner" role="region" aria-label="Install this shop">
        <img src="/storefront/img/logo.png" alt="" width="32" height="32" class="install-banner__mark">

        <p class="install-banner__copy">
            <span class="install-banner__title">Add Shaniena to your home screen</span>
            <span class="install-banner__body">Opens full screen, and works on a patchy connection.</span>
        </p>

        <button type="button" class="install-banner__go" @click="install">Add</button>
        <button type="button" class="install-banner__close" aria-label="Not now" @click="dismiss">&times;</button>
    </div>
</template>

<style scoped>
.install-banner {
    position: fixed;
    left: 1rem;
    right: 1rem;
    bottom: 1rem;
    z-index: 1040;
    display: flex;
    align-items: center;
    gap: .75rem;
    max-width: 30rem;
    margin: 0 auto;
    padding: .75rem 1rem;
    border-radius: .5rem;
    background: #111111;
    color: #ffffff;
    box-shadow: 0 .5rem 1.5rem rgba(0, 0, 0, .25);
}

.install-banner__mark {
    flex: 0 0 auto;
    border-radius: .25rem;
}

.install-banner__copy {
    flex: 1 1 auto;
    margin: 0;
    min-width: 0;
}

.install-banner__title {
    display: block;
    font-size: .875rem;
    font-weight: 600;
}

.install-banner__body {
    display: block;
    font-size: .75rem;
    opacity: .75;
}

.install-banner__go {
    flex: 0 0 auto;
    border: 0;
    border-radius: .25rem;
    padding: .4rem .9rem;
    background: #ffffff;
    color: #111111;
    font-size: .8125rem;
    font-weight: 600;
}

.install-banner__close {
    flex: 0 0 auto;
    border: 0;
    background: none;
    color: #ffffff;
    font-size: 1.25rem;
    line-height: 1;
    opacity: .7;
    padding: 0 .25rem;
}

.install-banner__close:hover,
.install-banner__close:focus-visible {
    opacity: 1;
}
</style>
