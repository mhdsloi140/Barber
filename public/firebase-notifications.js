// public/js/firebase-notifications.js

  import { initializeApp } from "https://www.gstatic.com/firebasejs/12.13.0/firebase-app.js";
  import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.13.0/firebase-analytics.js";

  const firebaseConfig = {
    apiKey: "AIzaSyA_if2fnykQlUH5RumzFcAiday7qaxnoV0",
    authDomain: "naemen-57c3f.firebaseapp.com",
    databaseURL: "https://naemen-57c3f-default-rtdb.firebaseio.com",
    projectId: "naemen-57c3f",
    storageBucket: "naemen-57c3f.firebasestorage.app",
    messagingSenderId: "125209052652",
    appId: "1:125209052652:web:79e6cdc684101844ec6cc9",
    measurementId: "G-0V53HENYD4"
  };

// تهيئة Firebase
const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

// طلب إذن الإشعارات
function requestPermission() {
    Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
            console.log(' إذن الإشعارات ممنوح');
            getFCMToken();
        } else {
            console.log(' تم رفض الإشعارات');
        }
    });
}

// الحصول على التوكن وإرساله للخادم
async function getFCMToken() {
    try {
        const token = await getToken(messaging, {
            vapidKey: 'YOUR_VAPID_KEY'
        });

        if (token) {
            console.log(' FCM Token:', token);
            // إرسال التوكن إلى الخادم
            await fetch('/api/fcm-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ fcm_token: token })
            });
        }
    } catch (error) {
        console.error(' خطأ في الحصول على التوكن:', error);
    }
}

// استقبال الإشعارات أثناء تصفح الصفحة
onMessage(messaging, (payload) => {
    console.log(' إشعار جديد:', payload);

    // عرض إشعار في المتصفح
    const notification = new Notification(payload.notification.title, {
        body: payload.notification.body,
        icon: '/favicon.ico',
        requireInteraction: true
    });

    // عند النقر على الإشعار
    notification.onclick = function() {
        if (payload.data?.action_url) {
            window.location.href = payload.data.action_url;
        }
    };
});

// بدء الطلب عند تحميل الصفحة
if (Notification.permission === 'default') {
    requestPermission();
}
