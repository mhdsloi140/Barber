<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نعيما | لوحة تحكم </title>

    <!-- Tailwind CSS CDN (بديل عن الملفات المسببة للمشكلة) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom CSS لتعديل اتجاه RTL -->
    <style>
        [dir="rtl"] {
            text-align: right;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #f0f2fa 100%);
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

        @media (min-width: 1281px) {
            .sidebar-ctrl-btn {
                display: none;
            }
        }

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
        }

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

        .footer-note {
            margin-top: 2.5rem;
            text-align: center;
            padding: 1rem;
            color: #7c6b9e;
            font-size: 0.8rem;
            border-top: 1px solid #e4e9f2;
        }
    </style>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Aref+Ruqaa:wght@400;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    @stack('styles')
</head>

<body>

    <aside id="naimaSidebar" class="naima-sidebar">
        <div class="relative flex flex-col items-center pt-8 pb-6 px-6 border-b border-white/20">
            <i class="fas fa-times-circle text-white text-2xl cursor-pointer absolute top-4 left-4"
                id="closeSidebarMobile"></i>
            <div
                class="w-28 h-28 rounded-full bg-white flex items-center justify-center shadow-xl overflow-hidden mb-4">
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
                <li><a href="{{ route('admin.dashboard') }}" class="sidebar-link"><i
                            class="fas fa-chart-line"></i><span>الرئيسية</span></a></li>
                <li><a href="{{ route('admin.center') }}" class="sidebar-link"><i class="fas fa-store"></i><span>إدارة
                            الصالونات</span></a></li>
                <li><a href="{{ route('ads.index') }}" class="sidebar-link"><i class="fas fa-bullhorn"></i><span>إدارة
                            الإعلانات</span></a></li>
                <li class="mt-4 pt-2 border-t border-white/20">
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="sidebar-link w-full text-right">
                            <i class="fas fa-sign-out-alt"></i><span>تسجيل الخروج</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <div id="sidebarToggleFloating" class="sidebar-ctrl-btn">
        <i class="fas fa-bars-staggered"></i>
    </div>

    @include('layout.navbar')

    <div class="main-content-area" id="mainContent">
        <div class="content-padding">
            @yield('content')
            <div class="footer-note"><i class="fas fa-heart text-rose-400"></i> نعيما - | 2025</div>
        </div>
    </div>

    <script>
        // ========== كود الـ Sidebar فقط (بدون Perfect Scrollbar) ==========
        var sidebarElement = document.getElementById('naimaSidebar');
        var toggleFloatBtn = document.getElementById('sidebarToggleFloating');
        var closeMobileBtn = document.getElementById('closeSidebarMobile');

        function isMobile() {
            return window.innerWidth < 1280;
        }

        function openSidebar() {
            if (isMobile()) {
                sidebarElement.classList.add('sidebar-visible');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeSidebar() {
            if (isMobile()) {
                sidebarElement.classList.remove('sidebar-visible');
                document.body.style.overflow = '';
            }
        }

        function toggleSidebar() {
            if (isMobile()) {
                if (sidebarElement.classList.contains('sidebar-visible')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
        }

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

        document.addEventListener('click', function(event) {
            if (isMobile() && sidebarElement && toggleFloatBtn) {
                if (!sidebarElement.contains(event.target) && !toggleFloatBtn.contains(event.target)) {
                    if (sidebarElement.classList.contains('sidebar-visible')) {
                        closeSidebar();
                    }
                }
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1280) {
                if (sidebarElement) {
                    sidebarElement.classList.remove('sidebar-visible');
                }
                document.body.style.overflow = '';
            }
        });

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

    @stack('scripts')

</body>

</html>
