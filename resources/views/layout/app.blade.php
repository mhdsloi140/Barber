<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نعيما | لوحة تحكم عصرية</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Aref+Ruqaa:wght@400;700&display=swap" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Tailwind CSS CDN (بديل عن الملفات المحلية المعقدة) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7ff 0%, #f0f2fa 100%);
            font-family: 'Cairo', 'Open Sans', sans-serif !important;
            min-height: 100vh;
        }

        /* ========== الشريط الجانبي ========== */
        .naima-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 280px;
            height: 100vh;
            z-index: 1050;
            background: linear-gradient(145deg, #4a0e6e 0%, #9b30ff 100%);
            box-shadow: -8px 0 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.2);
            transform: translateX(0);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            border-top-left-radius: 28px;
            border-bottom-left-radius: 28px;
        }

        .naima-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .naima-sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .naima-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.4);
            border-radius: 10px;
        }

        @media (max-width: 1280px) {
            .naima-sidebar {
                transform: translateX(100%);
                border-radius: 0;
                z-index: 9999;
            }

            .naima-sidebar.sidebar-visible {
                transform: translateX(0) !important;
            }
        }

        @media (min-width: 1281px) {
            .naima-sidebar {
                transform: translateX(0);
            }
        }

        /* زر فتح/غلق الـ Sidebar */
        .sidebar-ctrl-btn {
            position: fixed;
            bottom: 1.8rem;
            left: 1.8rem;
            right: auto;
            z-index: 1100;
            width: 58px;
            height: 58px;
            background: linear-gradient(125deg, #7c3aed, #db2777);
            border-radius: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 22px rgba(108, 43, 217, 0.45);
            cursor: pointer;
            transition: all 0.25s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
        }

        .sidebar-ctrl-btn i {
            font-size: 1.8rem;
        }

        .sidebar-ctrl-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 18px 28px rgba(108, 43, 217, 0.6);
        }

        @media (min-width: 1281px) {
            .sidebar-ctrl-btn {
                display: none;
            }
        }

        /* روابط الـ Sidebar */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 20px;
            margin: 6px 12px;
            border-radius: 18px;
            color: #fff;
            font-weight: 500;
            transition: all 0.2s;
            background: transparent;
            text-decoration: none;
        }

        .sidebar-link i {
            width: 28px;
            font-size: 1.35rem;
            text-align: center;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateX(-5px);
        }

        .active-sidebar {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* الـ Navbar العلوي */
        .naima-navbar {
            background: linear-gradient(135deg, #4a0e6e 0%, #9b30ff 100%);
            width: 100%;
            padding: 0.9rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1020;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .navbar-brand i {
            font-size: 1.8rem;
            color: #ffde7a;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        /* المحتوى الرئيسي */
        .main-content-area {
            transition: margin-right 0.3s ease;
            min-height: 100vh;
            width: 100%;
        }

        @media (min-width: 1281px) {
            .main-content-area {
                margin-right: 280px;
                width: calc(100% - 280px);
            }
        }

        .content-padding {
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .content-padding {
                padding: 1rem;
            }
        }

        /* تذييل الصفحة */
        .footer-note {
            margin-top: 2.5rem;
            text-align: center;
            padding: 1rem;
            color: #7c6b9e;
            font-size: 0.8rem;
            border-top: 1px solid #e4e9f2;
        }
    </style>

    @stack('styles')
</head>

<body>

    <!-- ========== الشريط الجانبي ========== -->
    <aside id="naimaSidebar" class="naima-sidebar">
        <div class="relative flex flex-col items-center pt-8 pb-6 px-6 border-b border-white/20">
            <i class="fas fa-times-circle text-white text-2xl cursor-pointer absolute top-4 left-4"
                id="closeSidebarMobile"></i>
            <div
                class="w-28 h-28 rounded-full bg-white/10 backdrop-blur-sm border border-white/30 flex items-center justify-center shadow-xl overflow-hidden mb-4">
                <img src="{{ asset('img/logo-new.png') }}" alt="شعار نعيما" class="w-full h-full object-cover"
                    onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-cut text-white text-4xl\'></i>'">
            </div>
            <div class="text-center">
                <h2 class="text-2xl font-bold text-white" style="font-family: 'Aref Ruqaa', serif;">نعيماً</h2>
                <p class="text-sm text-white/70 mt-1">لخدمات الحلاقة</p>
            </div>
        </div>
        <div class="flex-1 mt-4">
            <ul class="flex flex-col gap-1">
                <li>
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link">
                        <i class="fas fa-chart-line"></i>
                        <span>الرئيسية</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.center') }}" class="sidebar-link">
                        <i class="fas fa-store"></i>
                        <span>إدارة الصالونات</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('ads.index') }}" class="sidebar-link">
                        <i class="fas fa-bullhorn"></i>
                        <span>إدارة الإعلانات</span>
                    </a>
                </li>
                <li class="mt-4 pt-2 border-t border-white/20">
                    <form action="{{ route('admin.logout') }}" method="POST" id="logoutForm">
                        @csrf
                        <button type="submit" class="sidebar-link w-full text-right"
                            style="background: transparent; width: 100%;">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>تسجيل الخروج</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ========== زر فتح/غلق الـ Sidebar (للشاشات الصغيرة) ========== -->
    <div id="sidebarToggleFloating" class="sidebar-ctrl-btn">
        <i class="fas fa-bars-staggered"></i>
    </div>

    <!-- ========== الـ Navbar ========== -->
    @include('layout.navbar')

    <!-- ========== المحتوى الرئيسي ========== -->
    <div class="main-content-area" id="mainContent">
        <div class="content-padding">
            @yield('content')
            <div class="footer-note">
                <i class="fas fa-heart text-rose-400"></i> نعيما - 2025
            </div>
        </div>
    </div>

    <!-- ========== كود الـ Sidebar ========== -->
    <script>
        // عناصر الـ Sidebar
        var sidebarElement = document.getElementById('naimaSidebar');
        var toggleFloatBtn = document.getElementById('sidebarToggleFloating');
        var closeMobileBtn = document.getElementById('closeSidebarMobile');

        // التحقق إذا كانت الشاشة صغيرة (أقل من 1280px)
        function isMobile() {
            return window.innerWidth < 1280;
        }

        // فتح الـ Sidebar (للموبايل)
        function openSidebar() {
            if (isMobile()) {
                sidebarElement.classList.add('sidebar-visible');
                document.body.style.overflow = 'hidden';
            }
        }

        // غلق الـ Sidebar (للموبايل)
        function closeSidebar() {
            if (isMobile()) {
                sidebarElement.classList.remove('sidebar-visible');
                document.body.style.overflow = '';
            }
        }

        // تبديل حالة الـ Sidebar
        function toggleSidebar() {
            if (isMobile()) {
                if (sidebarElement.classList.contains('sidebar-visible')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
        }

        // ربط الأحداث بالأزرار
        if (toggleFloatBtn) {
            toggleFloatBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }

        if (closeMobileBtn) {
            closeMobileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeSidebar();
            });
        }

        // غلق الـ Sidebar عند النقر خارجه (للموبايل)
        document.addEventListener('click', function(event) {
            if (isMobile() && sidebarElement && toggleFloatBtn) {
                if (!sidebarElement.contains(event.target) && !toggleFloatBtn.contains(event.target)) {
                    if (sidebarElement.classList.contains('sidebar-visible')) {
                        closeSidebar();
                    }
                }
            }
        });

        // إعادة ضبط الـ Sidebar عند تغيير حجم النافذة
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1280) {
                sidebarElement.classList.remove('sidebar-visible');
                document.body.style.overflow = '';
            }
        });

        // تفعيل الرابط النشط في الـ Sidebar
        function setActiveLink() {
            var currentUrl = window.location.pathname;
            var links = document.querySelectorAll('.sidebar-link');
            for (var i = 0; i < links.length; i++) {
                links[i].classList.remove('active-sidebar');
                if (links[i].getAttribute('href') === currentUrl) {
                    links[i].classList.add('active-sidebar');
                }
            }
        }
        setActiveLink();
    </script>

    <!-- ========== Firebase Notifications (للمستخدمين المسجلين فقط) ========== -->
    @auth
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js"></script>

    <script>
        (function() {
            if (window._firebaseInitialized) return;
            window._firebaseInitialized = true;

            const firebaseConfig = {
                apiKey: "{{ env('FIREBASE_API_KEY') }}",
                authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
                projectId: "{{ env('FIREBASE_PROJECT_ID') }}",
                storageBucket: "{{ env('FIREBASE_STORAGE_BUCKET') }}",
                messagingSenderId: "{{ env('FIREBASE_MESSAGING_SENDER_ID') }}",
                appId: "{{ env('FIREBASE_APP_ID') }}"
            };

            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }

            const messaging = firebase.messaging();

            async function registerSW() {
                if ('serviceWorker' in navigator) {
                    try {
                        await navigator.serviceWorker.register('/firebase-messaging-sw.js');
                        console.log('Service Worker registered');
                    } catch (error) {
                        console.error('SW failed:', error);
                    }
                }
            }

            async function getTokenWithAuth() {
                try {
                    const permission = await Notification.requestPermission();
                    if (permission !== 'granted') return null;
                    await registerSW();
                    const token = await messaging.getToken({ vapidKey: '{{ env("FIREBASE_VAPID_KEY") }}' });
                    if (token) {
                        await fetch('/admin/fcm/token', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ fcm_token: token })
                        });
                        console.log('FCM Token saved');
                    }
                    return token;
                } catch (error) {
                    console.error('Token error:', error);
                    return null;
                }
            }

            setTimeout(function() {
                getTokenWithAuth();
            }, 2000);
        })();
    </script>
    @endauth

    @stack('scripts')

</body>

</html>