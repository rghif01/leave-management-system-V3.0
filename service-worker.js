/**
 * APM Leave Management - Service Worker
 * Provides offline support and caching
 */

const CACHE_NAME = 'apm-leave-v1.0';
const OFFLINE_URL = '/APM/offline/index.html';

// Files to cache for offline use
const PRECACHE_URLS = [
    '/APM/index.php',
    '/APM/offline/index.html',
    '/APM/assets/css/main.css',
    '/APM/assets/js/main.js',
    '/APM/assets/icons/icon-192.png',
];

// Install - pre-cache essential files
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(PRECACHE_URLS.map(url => new Request(url, { cache: 'reload' })))
                .catch(err => console.warn('Pre-cache failed for some URLs:', err));
        }).then(() => self.skipWaiting())
    );
});

// Activate - clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

// Fetch - network first, fallback to cache
self.addEventListener('fetch', event => {
    // Skip non-GET or API/POST requests
    if (event.request.method !== 'GET') return;
    if (event.request.url.includes('/api/')) return;
    if (event.request.url.includes('.php') && new URL(event.request.url).searchParams.has('action')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Cache successful responses for static assets
                if (response.ok && (
                    event.request.url.includes('/assets/') ||
                    event.request.url.includes('/APM/index.php')
                )) {
                    const resClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, resClone));
                }
                return response;
            })
            .catch(() => {
                // Offline fallback
                return caches.match(event.request).then(cached => {
                    if (cached) return cached;
                    // Return offline page for navigation requests
                    if (event.request.mode === 'navigate') {
                        return caches.match(OFFLINE_URL);
                    }
                });
            })
    );
});
