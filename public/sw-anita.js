/* Anita ERP — service worker mínimo (PWA). No cachea datos de negocio. */
const CACHE = 'anita-pwa-shell-v1';
const SHELL = [
  './assets/pwa/icon-192.png',
  './assets/pwa/icon-512.png',
  './manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  // Solo assets estáticos del mismo origen; el ERP siempre va a red.
  if (url.origin !== self.location.origin) return;
  if (!url.pathname.includes('/assets/') && !url.pathname.endsWith('manifest.webmanifest')) {
    return;
  }
  event.respondWith(
    caches.match(req).then((cached) =>
      cached ||
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((cache) => cache.put(req, copy));
        return res;
      }).catch(() => cached)
    )
  );
});
