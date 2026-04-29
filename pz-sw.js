// PadelZero Service Worker v1
const CACHE = 'pz-v2';

self.addEventListener('install', function(e) {
    self.skipWaiting();
});

self.addEventListener('activate', function(e) {
    e.waitUntil(clients.claim());
});

// Network-first: prova la rete, fallback cache
self.addEventListener('fetch', function(e) {
    if (e.request.method !== 'GET') return;
    e.respondWith(
        fetch(e.request)
            .then(function(res) {
                var clone = res.clone();
                caches.open(CACHE).then(function(c) { c.put(e.request, clone); });
                return res;
            })
            .catch(function() {
                return caches.match(e.request);
            })
    );
});
