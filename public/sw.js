/*
 * Shaniena storefront service worker.
 *
 * Two deliberate differences from the source's worker:
 *  - Product images are NOT cache-first. The source cached /assets/images/products/
 *    that way, so a re-uploaded photo never reached anyone who had seen the old one.
 *  - Payment and gateway hosts are never touched, and neither is anything under
 *    /admin — a stale console is worse than no console.
 */
const VERSION = 'shaniena-v2';
const OFFLINE_URL = '/offline.html';

const PRECACHE = [OFFLINE_URL];

// Build output is content-hashed, so it is safe to keep indefinitely.
const IMMUTABLE = /\/build\/assets\/.+\.(js|css|woff2?)$/;
const FONTS = /^https:\/\/fonts\.(googleapis|gstatic)\.com\//;
const NEVER = [/\/admin(\/|$)/, /senangpay\./, /bayarcash\./, /stripe\.com/];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (NEVER.some((pattern) => pattern.test(url.href))) return;

    // Hashed assets and web fonts: cache first, they never change in place.
    if (IMMUTABLE.test(url.pathname) || FONTS.test(url.href)) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
                return response;
            })),
        );
        return;
    }

    // Pages: network first, falling back to the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
});
