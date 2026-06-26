/**
 * Highly Optimized Service Worker - Frest App PWA
 */

const CACHE_NAME = 'frest-static-v2.0.6';
const DYNAMIC_CACHE_NAME = 'frest-dynamic-v2.0.6';
const MAX_DYNAMIC_ITEMS = 60;

const STATIC_ASSETS = [
  './index.php',
  './offline.html',
  './manifest.json',
  './assets/css/style.css',
  './assets/js/main.js',
  './assets/js/pwa.js',
  './assets/js/theme.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  // PWA_ICONS_START
  './uploads/system/pwa_icon_72.png',
  './uploads/system/pwa_icon_96.png',
  './uploads/system/pwa_icon_128.png',
  './uploads/system/pwa_icon_144.png',
  './uploads/system/pwa_icon_152.png',
  './uploads/system/pwa_icon_192.png',
  './uploads/system/pwa_icon_384.png',
  './uploads/system/pwa_icon_512.png',
  './uploads/system/pwa_icon_512_maskable.png'
  // PWA_ICONS_END
];

// Helper to limit cache size
const trimCache = (cacheName, maxItems) => {
  caches.open(cacheName).then(cache => {
    cache.keys().then(keys => {
      if (keys.length > maxItems) {
        cache.delete(keys[0]).then(() => trimCache(cacheName, maxItems));
      }
    });
  });
};

// Install SW - Pre-cache all essential static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('SW: Pre-caching static assets...');
      return cache.addAll(STATIC_ASSETS);
    })
  );
});

// Activate SW - Clean up older caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME && key !== DYNAMIC_CACHE_NAME) {
            console.log('SW: Deleting old cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch SW
self.addEventListener('fetch', event => {
  // Only handle HTTP/HTTPS (skip chrome-extension, etc.)
  if (!event.request.url.startsWith(self.location.origin) && !event.request.url.startsWith('http')) {
    return;
  }

  // Skip POST and other non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);

  // Skip Server-Sent Events (SSE) connections, uploads, admin PHP actions, and all dynamic PHP AJAX requests
  if (
    url.pathname.includes('sse_stream.php') ||
    url.pathname.includes('vote_poll_action.php') ||
    url.pathname.includes('react.php') ||
    url.pathname.includes('react_message.php') ||
    url.pathname.includes('send_message.php') ||
    url.pathname.includes('/admin/') ||
    (url.pathname.includes('.php') && event.request.mode !== 'navigate')
  ) {
    return;
  }

  // Strategy for HTML/Page requests (Navigate): Network First, fallback to cache, fallback to offline.html
  if (event.request.mode === 'navigate' || (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html'))) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Clone the response and save to dynamic cache
          const responseClone = response.clone();
          caches.open(DYNAMIC_CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
            trimCache(DYNAMIC_CACHE_NAME, MAX_DYNAMIC_ITEMS);
          });
          return response;
        })
        .catch(() => {
          return caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // Fallback to offline page
            return caches.match('./offline.html');
          });
        })
    );
    return;
  }

  // Strategy for static resources (CSS, JS, Fonts, CDN assets): Stale-While-Revalidate
  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      // Fetch in the background and update cache
      const fetchPromise = fetch(event.request).then(networkResponse => {
        if (networkResponse && networkResponse.status === 200) {
          const responseClone = networkResponse.clone();
          caches.open(DYNAMIC_CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
            trimCache(DYNAMIC_CACHE_NAME, MAX_DYNAMIC_ITEMS);
          });
        }
        return networkResponse;
      }).catch(err => {
        console.warn('SW: Dynamic fetch failed for', event.request.url, err);
      });

      // Return cached response if available, else wait for network fetch
      return cachedResponse || fetchPromise;
    })
  );
});

// Handle communication from clients (like skipWaiting message)
self.addEventListener('message', event => {
  if (event.data && event.data.action === 'skipWaiting') {
    console.log('SW: Received skipWaiting. Activating new version immediately...');
    self.skipWaiting();
  }
});
