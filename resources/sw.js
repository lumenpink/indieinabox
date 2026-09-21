self.addEventListener('push', (event) => {
    let data = {
        title: 'Indieinabox Alert',
        body: 'New notification received.',
        url: '/microsub/reader?channel=notifications',
        tag: 'default'
    };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: '/media/avatar.png',
        badge: '/media/avatar.png',
        tag: data.tag || 'notification',
        data: {
            url: data.url || '/microsub/reader?channel=notifications'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const urlToOpen = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/microsub/reader?channel=notifications';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (let client of windowClients) {
                if (client.url.includes(urlToOpen) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});
