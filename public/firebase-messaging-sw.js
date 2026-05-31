// public/firebase-messaging-sw.js

importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

// const firebaseConfig = {
//     apiKey: "",
//     authDomain: "",
//     projectId: "",
//     storageBucket: "",
//     messagingSenderId: "",
//     appId: ""
// };
const firebaseConfig = {
  apiKey: "AIzaSyDGuYSHmd8X3ZT5JGZXHHFYD-h-zBaUZuw",
  authDomain: "ne3imen.firebaseapp.com",
  projectId: "ne3imen",
  storageBucket: "ne3imen.firebasestorage.app",
  messagingSenderId: "1088928548925",
  appId: "1:1088928548925:web:17f0c3787ede4cb2510254",
  measurementId: "G-Y9JDXPEBM7"
};
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// تخصيص تصميم الإشعار
messaging.onBackgroundMessage((payload) => {
    console.log('[Service Worker] Received:', payload);

    const notificationTitle = payload.notification?.title || 'إشعار جديد';
    const notificationBody = payload.notification?.body || '';

    // تخصيص خيارات الإشعار
    const notificationOptions = {
        body: notificationBody,
        icon: '/img/logo.png',           // أيقونة الإشعار
        badge: '/img/badge.png',         // أيقونة صغيرة (للأندرويد)
        image: payload.data?.image_url || '/img/notification-image.png', // صورة كبيرة
        tag: 'naima-notification',       // لمنع تكرار الإشعارات
        renotify: true,                  // إعادة الإشعار إذا كان نفس الـ tag
        requireInteraction: true,        // يبقى الإشعار حتى يتفاعل معه المستخدم
        vibrate: [200, 100, 200],        // اهتزاز للجوال
        silent: false,                   // تشغيل الصوت
        sound: '/sounds/notification.mp3', // صوت مخصص (اختياري)

        // أزرار داخل الإشعار
        actions: [
            {
                action: 'view',
                title: ' عرض',
                icon: '/img/view-icon.png'
            },
            {
                action: 'dismiss',
                title: ' إغلاق',
                icon: '/img/close-icon.png'
            }
        ],

        // بيانات إضافية
        data: {
            url: payload.data?.action_url || '/',
            type: payload.data?.type || 'general',
            id: payload.data?.id || null,
            timestamp: Date.now()
        }
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});

// معالجة النقر على أزرار الإشعار أو الإشعار نفسه
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const action = event.action;
    const notificationData = event.notification.data;
    let urlToOpen = notificationData.url || '/';

    // معالجة الأزرار المختلفة
    if (action === 'view') {
        urlToOpen = notificationData.url;
    } else if (action === 'dismiss') {
        // إغلاق فقط بدون فتح رابط
        return;
    }

    // فتح النافذة
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(windowClients => {
                // البحث عن نافذة مفتوحة
                for (let client of windowClients) {
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                // فتح نافذة جديدة إذا لم توجد
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});
