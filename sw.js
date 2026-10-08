const CACHE_VERSION = 'agenda-daq-v15';
const SHELL_CACHE = `${CACHE_VERSION}-shell`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;
const SHELL_ASSETS = [
    './',
    './login.php',
    './manifest.webmanifest',
    './assets/css/tokens.css',
    './assets/css/base.css',
    './assets/css/components.css',
    './assets/css/pastel.css',
    './assets/js/app.js',
    './assets/js/contactos.js',
    './assets/js/seguimientos.js',
    './assets/js/panel.js',
    './assets/js/ajustes.js',
    './assets/js/notas.js',
    './assets/js/pwa.js',
    './assets/vendor/alpine.min.js',
    './assets/vendor/lucide/lucide.min.js',
    './assets/icons/icon-192.png',
    './assets/icons/icon-512.png',
    './assets/icons/icon-maskable-512.png',
    './assets/icons/apple-touch-icon.png',
    './assets/icons/icon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(
        keys.filter((key) => key.startsWith('agenda-daq-') && !key.startsWith(CACHE_VERSION)).map((key) => caches.delete(key)),
    )));
    self.clients.claim();
});

async function networkFirst(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
            return response;
        }
        const cached = await cache.match(request);
        if (cached) return cached;
        return response;
    } catch (error) {
        const cached = await cache.match(request);
        if (cached) return cached;
        if (request.destination === 'document') return (await caches.match('./')) || Response.error();
        return new Response(JSON.stringify({ ok: false, error: 'Sin conexión' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' },
        });
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (request.destination === 'document') {
        event.respondWith(networkFirst(request));
        return;
    }

    event.respondWith(caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response.ok) caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, response.clone()));
        return response;
    })));
});

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }
    const options = {
        body: payload.body || 'Tenés un recordatorio pendiente.',
        icon: 'assets/icons/icon-192.png',
        badge: 'assets/icons/icon-192.png',
        tag: payload.tag || 'agenda-daq',
        renotify: false,
        data: { url: payload.url || './' },
    };
    event.waitUntil(self.registration.showNotification(payload.title || 'Agenda DAQ', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destination = new URL(event.notification.data?.url || './', self.registration.scope).href;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (windows) => {
        for (const client of windows) {
            if (client.url.startsWith(self.location.origin)) {
                await client.focus();
                return client.navigate(destination);
            }
        }
        return clients.openWindow(destination);
    }));
});
