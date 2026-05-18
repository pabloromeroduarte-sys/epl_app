const CACHE_NAME = 'epl-v1';
const OFFLINE_URL = '/login.php';

// ── Install: precachea la shell básica ────────────────────────────────────────
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll([
                OFFLINE_URL,
                '/assets/css/epl.css',
                '/assets/img/logo-epl.png',
                '/assets/img/logo-epl-square.png',
                '/assets/img/favicon.png',
            ]).catch(function() { /* si alguno falla, igual instala */ });
        })
    );
    self.skipWaiting();
});

// ── Activate: limpia caches viejos ────────────────────────────────────────────
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            );
        })
    );
    self.clients.claim();
});

// ── Fetch: network-first, fallback a cache ────────────────────────────────────
self.addEventListener('fetch', function(event) {
    // Solo GET, ignorar chrome-extension y non-http
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith('http')) return;

    event.respondWith(
        fetch(event.request)
            .then(function(response) {
                // Clonar y guardar en cache solo respuestas válidas
                if (response && response.status === 200 && response.type === 'basic') {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                }
                return response;
            })
            .catch(function() {
                // Sin red: intenta desde cache, o muestra offline
                return caches.match(event.request).then(function(cached) {
                    return cached || caches.match(OFFLINE_URL);
                });
            })
    );
});

// ── Push notifications ────────────────────────────────────────────────────────
self.addEventListener('push', function(event) {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch(e) { data = { title: 'Elite Padel League', body: event.data.text() }; }

    const title   = data.title || 'Elite Padel League';
    const options = {
        body:    data.body  || '',
        icon:    data.icon  || '/assets/img/logo-epl-square.png',
        badge:   '/assets/img/favicon.png',
        data:    { url: data.url || '/dashboard.php' },
        vibrate: [200, 100, 200],
        requireInteraction: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data.url || '/';
    event.waitUntil(clients.matchAll({ type: 'window' }).then(function(cs) {
        for (let c of cs) {
            if (c.url === url && 'focus' in c) return c.focus();
        }
        if (clients.openWindow) return clients.openWindow(url);
    }));
});
