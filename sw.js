/**
 * SquirrelMail PWA Service Worker
 * Provides offline shell caching, background asset syncing, and PWA installability.
 */

const CACHE_NAME = 'squirrelmail-pwa-v1.5.74';

// Determine the base path where the service worker is located (supports subdirectories)
const SW_DIR = self.location.pathname.substring(0, self.location.pathname.lastIndexOf('/') + 1);

const PRECACHE_ASSETS = [
  SW_DIR + 'manifest.json',
  SW_DIR + 'offline.html',
  SW_DIR + 'assets/css/app.css',
  SW_DIR + 'assets/js/app.js',
  SW_DIR + 'assets/js/dompurify.min.js',
  SW_DIR + 'images/icon-192.png',
  SW_DIR + 'images/icon-512.png',
  SW_DIR + 'images/icon-maskable-192.png',
  SW_DIR + 'images/icon-maskable-512.png',
  SW_DIR + 'images/sm_logo.png',
  SW_DIR + 'images/apple-touch-icon.png',
  SW_DIR + 'images/favicon-32x32.png'
];

// Install Event: cache core app shell
self.addEventListener('install', (event) => {
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
    }).then(() => self.skipWaiting())
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

  // 1. Navigation requests (full HTML page navigations/refreshes)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(async () => {
        // Try matching cached offline fallback page
        const cachedOffline =
          (await caches.match(SW_DIR + 'offline.html')) ||
          (await caches.match('./offline.html')) ||
          (await caches.match('offline.html')) ||
          (await caches.match('/offline.html'));

        if (cachedOffline) {
          return cachedOffline;
        }

        // Guaranteed fallback HTML Response: prevents Uncaught TypeError: Failed to convert value to 'Response'
        return new Response(
          '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Offline - SquirrelMail</title><style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8fafc;color:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;padding:20px;box-sizing:border-box;}.card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:40px 32px;max-width:440px;width:100%;text-align:center;box-shadow:0 10px 25px -5px rgba(0,0,0,0.05);}h1{font-size:22px;font-weight:700;margin:0 0 12px;}p{color:#64748b;font-size:14.5px;line-height:1.6;margin:0 0 24px;}button{background:#2563eb;color:#fff;border:none;border-radius:10px;padding:12px 24px;font-size:14.5px;font-weight:600;cursor:pointer;}button:hover{background:#1d4ed8;}</style></head><body><div class="card"><h1>You\'re Currently Offline</h1><p>SquirrelMail cannot reach your mail server right now. Check your internet connection or retry below.</p><button type="button" onclick="window.location.reload()">Retry Connection</button></div><script>window.addEventListener("online",()=>window.location.reload());</script></body></html>',
          {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
          }
        );
      })
    );
    return;
  }

  // 2. Static Assets (CSS, JS, Images, Icons, Fonts, Manifests)
  const isStaticAsset =
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.ico') ||
    url.pathname.endsWith('.woff') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.ttf') ||
    url.pathname.endsWith('.webmanifest') ||
    url.pathname.endsWith('manifest.json');

  if (isStaticAsset) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          // Stale-while-revalidate: return cached version immediately, update in background
          fetch(request)
            .then((networkResponse) => {
              if (networkResponse && networkResponse.status === 200) {
                const responseClone = networkResponse.clone();
                caches.open(CACHE_NAME).then((cache) => {
                  cache.put(request, responseClone);
                });
              }
            })
            .catch(() => {
              // Silently ignore background update errors
            });
          return cachedResponse;
        }

        // Cache miss: fetch from network and cache
        return fetch(request)
          .then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
              const responseClone = networkResponse.clone();
              caches.open(CACHE_NAME).then((cache) => {
                cache.put(request, responseClone);
              });
            }
            return networkResponse;
          })
          .catch(() => {
            // When offline and asset is not cached, return safe empty response instead of undefined
            return new Response('', { status: 503, statusText: 'Service Unavailable' });
          });
      })
    );
    return;
  }

  // 3. Dynamic requests (PHP endpoints like webmail.php, AJAX workspace requests, attachments):
  // Let the browser handle them directly via its native fetch pipeline without SW respondWith interception.
  // This avoids converting failed network fetches to rejected promises inside respondWith and prevents
  // "TypeError: Failed to convert value to 'Response'".
});

// Message listener
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
