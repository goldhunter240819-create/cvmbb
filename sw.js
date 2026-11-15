// MBB Business Management System Service Worker
const CACHE_NAME = 'mbb-cache-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Pass-through for dynamic ERP interactions
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
