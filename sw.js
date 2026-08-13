const CACHE_NAME = 'kortzen-v4';
const ASSETS_TO_CACHE = [
  '/pwa-entry.php',
  '/cliente-dashboard.php',
  '/pwa-servicios.php',
  '/pwa-barberos.php',
  '/reservar.php',
  '/mis-citas.php',
  '/mi-perfil.php',
  '/cliente-login.php',
  '/',
  '/index.html',
  '/servicios.html',
  '/nosotros.html',
  '/galeria.html',
  '/contacto.html',
  '/css/variables.css',
  '/css/reset.css',
  '/css/base.css',
  '/css/pwa-native.css',
  '/css/components.css',
  '/css/layout.css',
  '/css/pages.css',
  '/css/animations.css',
  '/js/calendar-helper.js',
  '/js/main.js',
  '/assets/icons/favicon.png',
  '/manifest.json'
];

// Install Event - Caching basic resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(ASSETS_TO_CACHE);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate Event - Clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Stale-while-revalidate strategy for static resources
self.addEventListener('fetch', event => {
  // Exclude API requests and non-GET requests from caching
  if (event.request.url.includes('/api/') || event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          // Fetch new version in background to update cache
          fetch(event.request).then(networkResponse => {
            if (networkResponse.status === 200) {
              caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse));
            }
          }).catch(() => {});
          return cachedResponse;
        }

        return fetch(event.request).then(networkResponse => {
          // Cache newly fetched assets if valid
          if (networkResponse.status === 200 && networkResponse.type === 'basic') {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return networkResponse;
        });
      })
  );
});

// Push Notification Event
self.addEventListener('push', event => {
  let data = { title: 'KORTZEN Barbería', body: 'Tienes un nuevo recordatorio de tu cita ✂️', icon: '/assets/icons/favicon.png' };
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/assets/icons/favicon.png',
    badge: '/assets/icons/favicon.png',
    vibrate: [100, 50, 100],
    data: { url: data.url || '/cliente-dashboard.php' }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification Click Event
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const urlToOpen = event.notification.data.url || '/cliente-dashboard.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      for (let client of windowClients) {
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
