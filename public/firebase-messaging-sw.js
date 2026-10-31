importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js');
let config = {
        apiKey: "AIzaSyBGq5l_801rz5deoqJ2b2VsJA-oaSpmVyM",
        authDomain: "toriloup.firebaseapp.com",
        projectId: "toriloup",
        storageBucket: "toriloup.firebasestorage.app",
        messagingSenderId: "975651079418",
        appId: "1:975651079418:web:146dfb8dd0759f86ccf543",
 };
firebase.initializeApp(config);
const messaging = firebase.messaging();
messaging.onBackgroundMessage((payload) => {
    const notificationTitle = payload.notification?.title || payload.data?.title || 'Toriloup';
    const notificationOptions = {
        body: payload.notification?.body || payload.data?.body || '',
        icon: '/images/required/firebase-logo.png',
        data: payload.data || {}
    };
    self.registration.showNotification(notificationTitle, notificationOptions);
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const routeUrl = event.notification?.data?.route_url;
    const destination = routeUrl
        ? new URL('/admin/' + String(routeUrl).replace(/^\/+/, ''), self.location.origin).toString()
        : new URL('/', self.location.origin).toString();

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const client of windows) {
            if ('focus' in client) {
                client.navigate(destination);
                return client.focus();
            }
        }
        return clients.openWindow(destination);
    }));
});
