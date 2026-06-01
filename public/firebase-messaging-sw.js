// public/firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js');
const firebaseConfig = {
  apiKey: "AIzaSyDGuYSHmd8X3ZT5JGZXHHFYD-h-zBaUZuw",
  authDomain: "ne3imen.firebaseapp.com",
  projectId: "ne3imen",
  storageBucket: "ne3imen.firebasestorage.app",
  messagingSenderId: "1088928548925",
  appId: "1:1088928548925:web:17f0c3787ede4cb2510254",
  measurementId: "G-Y9JDXPEBM7",
  FIREBASE_VAPID_KEY:"BHFhstVTIWsCJiAc0uF-X8gFX0boR_B-GhfHCJNuWYpc8tTcCiq3-6IEVd7NslfkF-MAQO0VkokOLknV0XmLbaA"

};
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    console.log('📨 إشعار في الخلفية:', payload);

    const notificationTitle = payload.notification?.title || 'إشعار جديد';
    const notificationOptions = {
        body: payload.notification?.body || 'لديك إشعار جديد',
        icon: '/img/logo2.png',
        badge: '/img/logo2.png',
        vibrate: [200, 100, 200],
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});
