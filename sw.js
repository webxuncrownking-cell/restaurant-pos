/**
 * Restaurant POS - Progressive Web App Service Worker
 * Subdirectory-aware offline app shell caching.
 *
 * This file lives at the application root (next to index.php) so its default
 * scope includes /pos when the app is installed under an XAMPP subdirectory.
 */
const CACHE_NAME = 'restaurant-pos-shell-v3';
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

  // Never cache authenticated/dynamic HTML pages such as /devices, /shifts,
  // or /reports. A stale cached form can hide new validation handlers and
  // make a successful code update appear to have no effect.
  const acceptsHtml = (request.headers.get('accept') || '').includes('text/html');
  if (request.mode === 'navigate' || acceptsHtml) {
    event.respondWith(
      fetch(request).catch(() => new Response(
        '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="font-family:system-ui;padding:2rem"><h1>Admin pages require a connection</h1><p>Reconnect to open management pages. The offline POS is available at <a href="' + POS_URL + '">the POS terminal</a>.</p></body></html>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
      ))
    );
    return;
  }

  // Only cache known static files. Never cache arbitrary JSON/API responses
  // or PHP routes; /api/pos/bootstrap is handled explicitly above.
  const isStaticAsset = /\.(?:js|mjs|css|svg|png|jpe?g|webp|gif|ico|woff2?|ttf|eot|json)$/i.test(url.pathname);
  if (!isStaticAsset) {
    event.respondWith(
      fetch(request).catch(() => new Response('Offline; this resource requires a connection.', {
        status: 503,
        headers: { 'Content-Type': 'text/plain; charset=utf-8' }
      }))
    );
    return;
  }

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
