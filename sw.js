// MBB Business Management System - PWA Service Worker
const CACHE_NAME = 'mbb-mobile-pwa-v6';
const STATIC_ASSETS = [
    './manifest.json',
    './apk/manifest.json',
    './assets/images/logo.png',
    './assets/images/logoapk.png',
    './assets/images/logoapk-192.png',
    './assets/images/logoapk-512.png',
    './assets/css/mbb-theme.css',
    './assets/js/mbb-app.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Network-first strategy for live ERP transaction data
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
