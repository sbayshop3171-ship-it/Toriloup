importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js');
let config = {
        apiKey: "AIzaSyCnfhH_4U7BgXO2B-kA8Rkqsph0a708rno",
        authDomain: "testingapp-76286.firebaseapp.com",
        projectId: "testingapp-76286",
        storageBucket: "testingapp-76286.firebasestorage.app",
        messagingSenderId: "889245367533",
        appId: "1:889245367533:web:b3074ef47bfe497a7820e4",
        measurementId: "G-G11QET0QDG",
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
