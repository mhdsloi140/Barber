// public/firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

const firebaseConfig = {
    apiKey: "AIzaSyA_if2fnykQlUH5RumzFcAiday7qaxnoV0",
    authDomain: "naemen-57c3f.firebaseapp.com",
    databaseURL: "https://naemen-57c3f-default-rtdb.firebaseio.com",
    projectId: "naemen-57c3f",
    storageBucket: "naemen-57c3f.firebasestorage.app",
    messagingSenderId: "125209052652",
    appId: "1:125209052652:web:79e6cdc684101844ec6cc9"
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const title = payload.notification?.title || 'إشعار جديد';
    const options = {
        body: payload.notification?.body || '',
        icon: '/favicon.ico',
        data: payload.data || {}
    };
    self.registration.showNotification(title, options);
});
