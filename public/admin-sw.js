const CACHE_NAME = 'alre-admin-v2';
const OFFLINE_URL = '/saeiblauhjc';

// Assets à mettre en cache
const PRECACHE_ASSETS = [
    '/css/admin.css',
    '/images/favicon.png',
    '/images/android-chrome-192x192.png',
    '/images/android-chrome-512x512.png'
];

// Installation du service worker
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activation et nettoyage des anciens caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(cacheName => cacheName !== CACHE_NAME)
                    .map(cacheName => caches.delete(cacheName))
            );
        }).then(() => self.clients.claim())
    );
});

// Stratégie Network First pour les pages, Cache First pour les assets
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorer les requêtes non-GET
    if (request.method !== 'GET') return;

    // Ignorer les requêtes externes
    if (url.origin !== location.origin) return;

    // Assets statiques : Cache First
    if (request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font') {
        event.respondWith(
            caches.match(request).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then(response => {
                    if (response.ok) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(request, responseClone);
                        });
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Pages : Network First avec fallback
    event.respondWith(
        fetch(request)
            .then(response => {
                return response;
            })
            .catch(() => {
                return caches.match(request);
            })
    );
});

// === PUSH NOTIFICATIONS ===
self.addEventListener('push', event => {
    const data = event.data?.json() ?? {};

    const options = {
        body: data.body || 'Vous avez un rappel',
        icon: '/images/android-chrome-192x192.png',
        badge: '/images/android-chrome-192x192.png',
        tag: data.tag || 'event-reminder',
        data: {
            url: data.url || '/saeiblauhjc/calendar'
        },
        requireInteraction: true,
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Rappel', options)
    );
});

// Clic sur notification
self.addEventListener('notificationclick', event => {
    event.notification.close();

    const url = event.notification.data?.url || '/saeiblauhjc/calendar';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                for (const client of clientList) {
                    if (client.url.includes('/saeiblauhjc') && 'focus' in client) {
                        return client.focus();
                    }
                }
                return clients.openWindow(url);
            })
    );
});
