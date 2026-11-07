// Service worker Jajapo: aset build disimpan, halaman terakhir dipakai saat sinyal kampus lemah.
const ASSETS = 'jajapo-assets-v1';
const PAGES = 'jajapo-pages-v1';

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
