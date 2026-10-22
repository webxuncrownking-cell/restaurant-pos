/**
 * Restaurant POS - Progressive Web App Service Worker
 * Subdirectory-aware offline app shell caching.
 *
 * This file lives at the application root (next to index.php) so its default
 * scope includes /pos when the app is installed under an XAMPP subdirectory.
 */
const CACHE_NAME = 'restaurant-pos-shell-v2';
const APP_BASE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const appUrl = (path) => `${APP_BASE_PATH}${path.startsWith('/') ? path : '/' + path}`;

const POS_URL = appUrl('/pos');
const POS_BOOTSTRAP_PREFIX = appUrl('/api/pos/bootstrap');
const POS_DB_URL = appUrl('/public/assets/js/pos-db.js');

const PRECACHE_URLS = [
  POS_URL,
  POS_DB_URL,
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .catch((error) => {
        // Keep installation possible when a CDN is temporarily unavailable.
        console.warn('[ServiceWorker] Some assets could not be pre-cached:', error);
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('restaurant-pos-shell-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  const isAppOrigin = url.origin === self.location.origin;
  const isAppPath = url.pathname === APP_BASE_PATH || url.pathname.startsWith(APP_BASE_PATH + '/');

  // The checkout/sync POST requests must always go to the server and are never cached.
  if (!isAppOrigin || !isAppPath) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request))
    );
    return;
  }

  if (url.pathname === POS_URL || url.pathname.startsWith(POS_BOOTSTRAP_PREFIX)) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            const cacheKey = url.pathname === POS_URL ? POS_URL : request;
            void caches.open(CACHE_NAME).then((cache) => cache.put(cacheKey, responseToCache));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          if (cached) return cached;

          if (url.pathname === POS_URL) {
            const shell = await caches.match(POS_URL);
            if (shell) return shell;
          }

          return new Response(JSON.stringify({ error: 'Offline; no cached POS data is available.' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
          });
        })
    );
    return;
  }

  // Cache-first for app/static assets; cache successful same-origin responses.
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;

      return fetch(request).then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          void caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
        }
        return response;
      }).catch(() => new Response('Offline; this asset is not cached.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' }
      }));
    })
  );
});
