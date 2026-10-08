/**
 * SquirrelMail PWA Service Worker
 * Provides offline shell caching, background asset syncing, and PWA installability.
 */

const CACHE_NAME = 'squirrelmail-pwa-v1.5.72';

const PRECACHE_ASSETS = [
  './manifest.json',
  './offline.html',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/js/dompurify.min.js',
  './images/icon-192.png',
  './images/icon-512.png',
  './images/icon-maskable-192.png',
  './images/icon-maskable-512.png',
  './images/sm_logo.png',
  './images/apple-touch-icon.png',
  './images/favicon-32x32.png'
];

// Install Event: cache core app shell
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Use catch for individual items so precache does not fail if an optional asset is missing
      return Promise.allSettled(
        PRECACHE_ASSETS.map((url) =>
          cache.add(url).catch((err) => {
            console.warn('[SquirrelMail SW] Failed to precache:', url, err);
          })
        )
      );
    })
  );
});

// Activate Event: purge outdated caches and claim clients
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((name) => {
          if (name.startsWith('squirrelmail-pwa-') && name !== CACHE_NAME) {
            console.log('[SquirrelMail SW] Removing legacy cache:', name);
            return caches.delete(name);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event: handle requests
self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Ignore non-GET requests (e.g. POST form submissions, action redirects)
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  // Ignore cross-origin requests
  if (url.origin !== self.location.origin) {
    return;
  }

  // Navigation requests (HTML pages)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .catch(() => {
          return caches.match('./offline.html');
        })
    );
    return;
  }

  // Static Assets (CSS, JS, Images, Icons, Fonts)
  const isStaticAsset =
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.ico') ||
    url.pathname.endsWith('.woff') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.ttf');

  if (isStaticAsset) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        // Return cached version if found, while updating cache in background
        const networkFetch = fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return networkResponse;
        }).catch(() => cachedResponse);

        return cachedResponse || networkFetch;
      })
    );
    return;
  }

  // Default network fetch
  event.respondWith(
    fetch(request).catch(() => {
      return caches.match(request);
    })
  );
});

// Message listener
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
