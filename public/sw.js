/**
 * Restaurant POS - Progressive Web App (PWA) Service Worker
 * Offline App Shell Caching & Background Network Resilience
 */

const CACHE_NAME = 'restaurant-pos-shell-v1';
const PRECACHE_URLS = [
  '/pos',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
  '/public/assets/js/pos-db.js'
];

// Install: Pre-cache static shell assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_URLS).catch((err) => {
        console.warn('[ServiceWorker] Some pre-cache assets could not be loaded immediately:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate: Clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keyList) => {
      return Promise.all(
        keyList.map((key) => {
          if (key !== CACHE_NAME) {
            console.log('[ServiceWorker] Removing old cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Strategy depending on request type
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Never cache POST requests or real-time sync API calls
  if (event.request.method !== 'GET' || url.pathname.startsWith('/api/pos/sync')) {
    return;
  }

  // Network-first with Cache fallback for POS Terminal UI and API bootstrap
  if (url.pathname === '/pos' || url.pathname.startsWith('/api/pos/bootstrap')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          return caches.match(event.request).then((cached) => {
            if (cached) return cached;
            if (url.pathname === '/pos') {
              return caches.match('/pos');
            }
            return new Response(JSON.stringify({ error: 'Offline, cached data unavailable' }), {
              headers: { 'Content-Type': 'application/json' }
            });
          });
        })
    );
    return;
  }

  // Cache-first with Network fallback for static assets (CDN styles, scripts, fonts)
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }
      return fetch(event.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      }).catch((err) => {
        console.warn('[ServiceWorker] Network fetch failed for:', event.request.url);
      });
    })
  );
});
