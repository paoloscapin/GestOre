self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  return self.clients.claim();
});

// 🔔 Ricezione notifica
self.addEventListener('push', function (event) {
  let data = {};

  try {
    data = event.data.json();
  } catch (e) {
    data = {
      title: 'GestOre',
      body: event.data ? event.data.text() : 'Nuova notifica'
    };
  }

  const title = data.title || 'GestOre';
  const options = {
    body: data.body || '',
    icon: '/GestOre/img/icons/icon-192.png',
    badge: '/GestOre/img/icons/icon-72.png',
    tag: data.tag || ('gestore-' + Date.now()),
    renotify: true,
    silent: false,
    timestamp: Date.now(),
    requireInteraction: true,
    vibrate: [180, 80, 180],
    data: {
      url: data.url || '/GestOre/index.php'
    }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// 👉 click sulla notifica
self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  const url = event.notification.data?.url || '/GestOre/index.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(function (clientList) {
        for (let client of clientList) {
          if (client.url.includes('/GestOre') && 'focus' in client) {
            return client.focus();
          }
        }
        return clients.openWindow(url);
      })
  );
});
