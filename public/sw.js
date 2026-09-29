// GHASIDO service worker: makes the app installable and shows an offline
// page when there is no connection. Pages and Inertia/API responses are never
// cached (they hold private, always-current data); only the hashed build
// assets and the brand images are, so a new deploy is picked up at once.
const VERSION = 'ghasido-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/pwa/icon-192.png',
    '/brand/ghasido-logo.png',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== VERSION)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Page loads: always the network; the offline page only when it fails.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches
                    .match(OFFLINE_URL)
                    .then((cached) => cached || Response.error()),
            ),
        );

        return;
    }

    // Vite's build files have a content hash in their name: safe to keep.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.open(VERSION).then((cache) =>
                cache.match(request).then(
                    (cached) =>
                        cached ||
                        fetch(request).then((response) => {
                            if (response.ok) {
                                cache.put(request, response.clone());
                            }

                            return response;
                        }),
                ),
            ),
        );
    }
});
