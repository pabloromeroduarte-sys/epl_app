self.addEventListener('push', function(event) {
    if (!event.data) return;
    let data = {};
    try { data = event.data.json(); } catch(e) { data = { title: 'Elite Padel League', body: event.data.text() }; }

    const title   = data.title || 'Elite Padel League';
    const options = {
        body:    data.body  || '',
        icon:    data.icon  || '/assets/img/logo-epl-square.png',
        badge:   '/assets/img/favicon.png',
        data:    { url: data.url || '/dashboard.php' },
        vibrate: [200, 100, 200],
        requireInteraction: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const url = event.notification.data.url || '/';
    event.waitUntil(clients.matchAll({ type: 'window' }).then(function(cs) {
        for (let c of cs) {
            if (c.url === url && 'focus' in c) return c.focus();
        }
        if (clients.openWindow) return clients.openWindow(url);
    }));
});
