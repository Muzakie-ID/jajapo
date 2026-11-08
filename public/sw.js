// Service worker Jajapo: aset build disimpan, halaman terakhir dipakai saat sinyal kampus lemah.
const ASSETS = 'jajapo-assets-v2';
const PAGES = 'jajapo-pages-v2';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => ![ASSETS, PAGES].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Aset build bernama hash: ambil dari cache dulu.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((res) => {
                        const copy = res.clone();
                        caches.open(ASSETS).then((c) => c.put(request, copy));
                        return res;
                    }),
            ),
        );
        return;
    }

    // Halaman pembeli (bukan panel, bukan API): jaringan dulu, cache kalau gagal.
    const isPage = request.mode === 'navigate' || request.headers.get('X-Inertia') === 'true';
    const cacheable = isPage && !url.pathname.startsWith('/admin') && !url.pathname.startsWith('/seller') && !url.pathname.startsWith('/bayar');
    if (cacheable) {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    if (res.ok) {
                        const copy = res.clone();
                        caches.open(PAGES).then((c) => c.put(request, copy));
                    }
                    return res;
                })
                .catch(() => caches.match(request).then((hit) => hit || caches.match('/'))),
        );
    }
});

// Notifikasi push: tampilkan, lalu buka halaman tujuannya saat diketuk.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Jajapo', body: event.data ? event.data.text() : '' };
    }
    const title = data.title || 'Jajapo';
    event.waitUntil(
        self.registration.showNotification(title, {
            body: data.body || '',
            icon: data.icon || '/icons/icon-192.png',
            badge: data.badge || '/icons/icon-192.png',
            tag: data.tag,
            data: { url: (data.data && data.data.url) || '/notifikasi' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data && event.notification.data.url ? event.notification.data.url : '/', self.location.origin).href;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            const open = wins.find((w) => w.url === target);
            if (open) return open.focus();
            return self.clients.openWindow(target);
        }),
    );
});
