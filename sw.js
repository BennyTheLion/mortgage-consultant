// Push service worker for the admin (advisor) notifications.
self.addEventListener('push', function (event) {
  var data = { title: 'תור חדש', body: '', url: 'admin.php' };
  try {
    if (event.data) data = Object.assign(data, event.data.json());
  } catch (e) {}

  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: 'images/icon-192.png',
      badge: 'images/icon-192.png',
      data: { url: data.url },
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = new URL(event.notification.data && event.notification.data.url || 'admin.php', self.location.origin).href;
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
      for (var i = 0; i < clients.length; i++) {
        if (clients[i].url === url && 'focus' in clients[i]) return clients[i].focus();
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});
