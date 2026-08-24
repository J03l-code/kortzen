const CACHE_NAME = 'kortzen-v6';
const ASSETS_TO_CACHE = [
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
  '/js/pwa.js',
  '/assets/icons/favicon.png',
  '/manifest.json'
];

// Install Event - Caching basic resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
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

// Fetch Event - Never intercept document navigation or PHP requests to prevent Safari redirection errors
self.addEventListener('fetch', event => {
  // Exclude page navigations, PHP files, API requests and non-GET requests from SW interception
  if (
    event.request.mode === 'navigate' ||
    event.request.url.includes('.php') ||
    event.request.url.includes('/api/') ||
    event.request.method !== 'GET'
  ) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          fetch(event.request).then(networkResponse => {
            if (networkResponse.status === 200 && networkResponse.type === 'basic') {
              caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse));
            }
          }).catch(() => {});
          return cachedResponse;
        }

        return fetch(event.request).then(networkResponse => {
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
  let data = { 
    title: '✂️ KORTZEN Barbería: Recordatorio de Cita', 
    body: 'Tienes un nuevo recordatorio de tu cita. Toca aquí para confirmar asistencia.', 
    icon: '/assets/icons/favicon.png',
    url: '/cliente-dashboard.php'
  };

  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      if (event.data.text()) data.body = event.data.text();
    }
  }

  const promiseFetchAndShow = fetch('/api/check_pending_pwa_notifications.php')
    .then(res => res.json())
    .then(resData => {
      if (resData && resData.pending && resData.notification) {
        const n = resData.notification;
        return self.registration.showNotification(n.title || data.title, {
          body: n.body || data.body,
          icon: n.icon || data.icon,
          badge: '/assets/icons/favicon.png',
          vibrate: [200, 100, 200],
          data: { url: n.url || data.url }
        });
      } else {
        return self.registration.showNotification(data.title, {
          body: data.body,
          icon: data.icon,
          badge: '/assets/icons/favicon.png',
          vibrate: [200, 100, 200],
          data: { url: data.url }
        });
      }
    })
    .catch(() => {
      return self.registration.showNotification(data.title, {
        body: data.body,
        icon: data.icon,
        badge: '/assets/icons/favicon.png',
        vibrate: [200, 100, 200],
        data: { url: data.url }
      });
    });

  event.waitUntil(promiseFetchAndShow);
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
