/* Nova Service Worker – macht die App installierbar (PWA).
   Wichtig: Es werden NUR statische Assets gecacht (CSS/JS/Icons), niemals
   HTML-Seiten, PDFs oder Daten – die enthalten vertrauliche/dynamische Inhalte. */
const CACHE = 'nova-static-v2';
const ASSETS = [
    '/assets/css/app.css',
    '/assets/js/app.js',
    '/assets/img/icon-192.png',
    '/assets/img/icon-512.png',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') {
        return; // POST/Logout etc. immer direkt ans Netz
    }
    const url = new URL(req.url);

    // Nur eigene statische Assets cachen (stale-while-revalidate).
    if (url.origin === location.origin && url.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.open(CACHE).then((cache) =>
                cache.match(req).then((cached) => {
                    const network = fetch(req).then((resp) => {
                        if (resp && resp.status === 200) {
                            cache.put(req, resp.clone());
                        }
                        return resp;
                    }).catch(() => cached);
                    return cached || network;
                })
            )
        );
        return;
    }

    // Alles andere (Seiten, PDFs, Daten): immer Netzwerk, kein Cache.
    event.respondWith(fetch(req));
});
