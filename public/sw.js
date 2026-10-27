/**
 * Restaurant POS - Progressive Web App (PWA) Service Worker
 * Scope-aware app-shell caching for root and subdirectory deployments.
 */

const CACHE_NAME = 'restaurant-pos-shell-v2';

// Derive paths from the registered service-worker scope so this also works
// when the app is hosted in a subdirectory such as /restaurant/.
const SCOPE_URL = new URL(self.registration.scope);
const APP_ROOT = SCOPE_URL.pathname.replace(/\/$/, '');
const appPath = (path) => `${APP_ROOT}${path.startsWith('/') ? path : '/' + path}`;
const POS_PATH = appPath('/pos');
const BOOTSTRAP_PREFIX = appPath('/api/pos/bootstrap');
const SYNC_PREFIX = appPath('/api/pos/sync');

const PRECACHE_URLS = [
  POS_PATH,
  appPath('/public/assets/css/bootstrap.min.css'),
  appPath('/public/assets/js/bootstrap.bundle.min.js'),
  appPath('/public/assets/js/pos-db.js'),
  appPath('/manifest.json'),
  // Keep the current icon stylesheet available for first-load caching when
  // the CDN responds. Local icon assets can replace this dependency later.
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
];

// Cache shell entries independently. One unavailable CDN resource must not
// prevent the local app assets from being cached.
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      await Promise.all(PRECACHE_URLS.map(async (assetUrl) => {
        try {
          await cache.add(assetUrl);
        } catch (error) {
          console.warn('[ServiceWorker] Unable to pre-cache:', assetUrl, error);
        }
      }));
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keyList) => Promise.all(
      keyList.map((key) => {
        if (key.startsWith('restaurant-pos-shell-') && key !== CACHE_NAME) {
          return caches.delete(key);
        }
        return undefined;
      })
    )).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Do not cache mutations or order-sync responses.
  if (request.method !== 'GET' || url.origin !== self.location.origin ||
      url.pathname.startsWith(SYNC_PREFIX)) {
    return;
  }

  const isPosPage = url.pathname === POS_PATH || url.pathname === POS_PATH + '/';
  const isBootstrap = url.pathname === BOOTSTRAP_PREFIX || url.pathname.startsWith(BOOTSTRAP_PREFIX + '/');

  // Network-first for the terminal and bootstrap data. Fall back only to an
  // exact cached request; never substitute a different query-string response.
  if (isPosPage || isBootstrap) {
    event.respondWith(
      fetch(request).then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const copy = response.clone();
          event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)));
        }
        return response;
      }).catch(async () => {
        const cached = await caches.match(request, { ignoreSearch: false });
        if (cached) return cached;
        if (isPosPage) {
          const shell = await caches.match(POS_PATH);
          if (shell) return shell;
        }
        return new Response(JSON.stringify({
          error: 'Offline and no cached response is available.',
          offline: true
        }), {
          status: 503,
          headers: { 'Content-Type': 'application/json; charset=utf-8' }
        });
      })
    );
    return;
  }

  // Cache-first for same-origin static assets, with network fallback.
  if (url.pathname.startsWith(appPath('/public/')) ||
      url.pathname === appPath('/manifest.json')) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        if (response && response.status === 200 && response.type === 'basic') {
          const copy = response.clone();
          event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)));
        }
        return response;
      }))
    );
  }
});
