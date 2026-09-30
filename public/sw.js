// GHASIDO service worker: only what browsers need to offer "Install app".
// It caches nothing, so every page and answer still goes to the server
// (progress is server-side truth, PROG-01).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);
self.addEventListener('fetch', () => {});
