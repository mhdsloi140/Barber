{{-- resources/views/layout/app.blade.php --}}

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نعيما | لوحة تحكم عصرية</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Aref+Ruqaa:wght@400;700&family=Cairo:wght@300;400;700&display=swap"
        rel="stylesheet">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Nucleo Icons -->
    <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/nucleo-svg.css') }}" rel="stylesheet">

    <!-- Main Styling -->
    <link href="{{ asset('assets/css/soft-ui-dashboard-tailwind.css?v=1.0.5') }}" rel="stylesheet">

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

        /* ========== الشريط الجانبي الأنيق ========== */
        .naima-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 280px;
            height: 100vh;
            z-index: 1050;
            background: linear-gradient(145deg, #4a0e6e 0%, #9b30ff 100%);
            backdrop-filter: blur(0px);
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
            }

            .naima-sidebar.sidebar-visible {
                transform: translateX(0);
                box-shadow: -5px 0 25px rgba(0, 0, 0, 0.3);
            }
        }

        /* زر التحكم الدائري الأيقوني */
        .sidebar-ctrl-btn {
            position: fixed;
            bottom: 1.8rem;
            right: 1.8rem;
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
            transition: transform 0.2s;
        }

        .sidebar-ctrl-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 18px 28px rgba(108, 43, 217, 0.6);
            background: linear-gradient(125deg, #8b4dff, #e83e8c);
        }

        /* روابط السايد بار */
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

        /* ========== NAVBAR جديدة بنفس لون السايد بار ========== */
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

        .navbar-icons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .navbar-icons i {
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            transition: 0.2s;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .navbar-icons i:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        /* المحتوى الرئيسي */
        .main-content-area {
            transition: margin-right 0.3s ease;
            min-height: 100vh;
            width: 100%;
        }

        @media (min-width: 1280px) {
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

        /* بطاقات إحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            border-radius: 28px;
            padding: 1.3rem;
            transition: all 0.25s;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.03), 0 6px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(128, 90, 213, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 25px 35px -12px rgba(108, 43, 217, 0.2);
            border-color: rgba(108, 43, 217, 0.3);
        }

        .icon-bg-grad {
            background: linear-gradient(125deg, #8b5cf6, #ec4899);
            width: 54px;
            height: 54px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 8px 14px rgba(139, 92, 246, 0.3);
        }

        /* جدول متجاوب */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 24px;
        }

        .custom-table {
            min-width: 600px;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            background: #f8f9ff;
            padding: 1rem 1rem;
            font-weight: 700;
            font-size: 0.75rem;
            color: #4a4e69;
        }

        .custom-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid #edeff5;
        }

        .badge-status {
            padding: 5px 12px;
            border-radius: 60px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-action {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            color: #6c5ce7;
            background: #f3f0ff;
        }

        .btn-delete {
            color: #e84393;
            background: #fff0f5;
        }

        .btn-view {
            color: #3498db;
            background: #e8f4ff;
        }

        .btn-edit:hover,
        .btn-delete:hover,
        .btn-view:hover {
            transform: scale(1.02);
        }

        .footer-note {
            margin-top: 2.5rem;
            text-align: center;
            padding: 1rem;
            color: #7c6b9e;
            font-size: 0.8rem;
            border-top: 1px solid #e4e9f2;
        }

        .dir-ltr {
            direction: ltr;
            display: inline-block;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .pagination .page-item {
            list-style: none;
        }

        .pagination .page-link {
            padding: 8px 12px;
            border-radius: 8px;
            background: #f3f4f6;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination .page-link:hover {
            background: #6c5ce7;
            color: white;
        }

        .pagination .active .page-link {
            background: #6c5ce7;
            color: white;
        }

        @media (max-width: 640px) {
            .stats-grid {
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .naima-navbar {
                padding: 0.7rem 1rem;
            }

            .navbar-brand {
                font-size: 1.2rem;
            }
        }

        /* تنسيق رسائل الخطأ */
        .error-text {
            color: #dc2626;
            font-size: 0.7rem;
            margin-top: 0.3rem;
            margin-right: 0.5rem;
        }

        .hidden {
            display: none;
        }
    </style>

    @stack('styles')
</head>

<body>

    <!-- ========== الشريط الجانبي ========== -->
    <aside id="naimaSidebar" class="naima-sidebar">
        <div class="flex items-center justify-between px-6 py-6 border-b border-white/20">
            <div class="flex items-center gap-5">

                <!-- أيقونة داخل دائرة -->
                <div
                    class="w-16 h-16 bg-gradient-to-br from-amber-400 to-amber-600 rounded-2xl flex items-center justify-center shadow-xl">
                    <i class="fas fa-cut text-purple-300 text-xl"></i>
                </div>

                <!-- اسم النظام -->
                <div class="flex flex-col leading-tight">
                    <span class="text-7xl font-bold text-white" style="font-family: 'Aref Ruqaa', serif;">
                        نعيماً
                    </span>
                    <span class="text-7xl font-bold text-white"  style="font-family: 'Aref Ruqaa', serif;">
                        لخدمات الحلاقة
                    </span>
                </div>

            </div>



            <i class="fas fa-times-circle text-white text-2xl cursor-pointer opacity-80 hover:opacity-100 transition close-sidebar-mobile"
                id="closeSidebarMobile"></i>
        </div>

        <div class="flex-1 mt-4">
            <ul class="flex flex-col gap-1">
                <li>
                    <a href="{{ route('admin.dashboard') }}" id="dashboardNavLink" class="sidebar-link">
                        <i class="fas fa-chart-line"></i>
                        <span>الرئيسية</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.center') }}" id="centersNavLink" class="sidebar-link">
                        <i class="fas fa-store"></i>
                        <span>إدارة الصالونات</span>
                    </a>
                </li>

                {{-- زر تسجيل الخروج --}}
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

    <!-- زر التحكم بالشريط الجانبي -->
    <div id="sidebarToggleFloating" class="sidebar-ctrl-btn">
        <i class="fas fa-bars-staggered"></i>
    </div>

    <!-- ========== NAVBAR ========== -->
    @include('layout.navbar')

    <!-- المحتوى الرئيسي -->
    <div class="main-content-area" id="mainContent">
        <div class="content-padding">
            @yield('content')
            <div class="footer-note">
                <i class="fas fa-heart text-rose-400"></i> نعيما - | 2025
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}" async></script>
    <script src="{{ asset('assets/js/soft-ui-dashboard-tailwind.js?v=1.0.5') }}" async></script>

    <script>
        // التحكم بالشريط الجانبي
        const sidebarElement = document.getElementById('naimaSidebar');
        const toggleFloatBtn = document.getElementById('sidebarToggleFloating');
        const closeMobileBtn = document.getElementById('closeSidebarMobile');

        function openSidebar() {
            if(window.innerWidth < 1280) sidebarElement.classList.add('sidebar-visible');
        }
        function closeSidebarManual() {
            if(window.innerWidth < 1280) sidebarElement.classList.remove('sidebar-visible');
        }
        function toggleSidebar() {
            if(window.innerWidth < 1280) {
                if(sidebarElement.classList.contains('sidebar-visible')) closeSidebarManual();
                else openSidebar();
            }
        }

        if(toggleFloatBtn) toggleFloatBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSidebar();
        });
        if(closeMobileBtn) closeMobileBtn.addEventListener('click', () => closeSidebarManual());

        // إغلاق السايد بار عند النقر خارجها
        document.addEventListener('click', function(event) {
            const isMobile = window.innerWidth < 1280;
            if(isMobile && sidebarElement && toggleFloatBtn) {
                if(!sidebarElement.contains(event.target) && !toggleFloatBtn.contains(event.target) && sidebarElement.classList.contains('sidebar-visible')) {
                    closeSidebarManual();
                }
            }
        });

        // عند تغيير حجم الشاشة
        window.addEventListener('resize', function() {
            if(window.innerWidth >= 1280) {
                sidebarElement.classList.remove('sidebar-visible');
                sidebarElement.style.transform = '';
            } else {
                if(!sidebarElement.classList.contains('sidebar-visible')) sidebarElement.style.transform = 'translateX(100%)';
                else sidebarElement.style.transform = 'translateX(0)';
            }
        });

        // تفعيل الرابط النشط بناءً على URL الحالي
        function setActiveLink() {
            const currentUrl = window.location.pathname;
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.classList.remove('active-sidebar');
                if(link.getAttribute('href') === currentUrl) {
                    link.classList.add('active-sidebar');
                }
            });
        }

        // تهيئة الحالة الأولية للشريط الجانبي
        if(window.innerWidth < 1280) sidebarElement.style.transform = 'translateX(100%)';
        else sidebarElement.style.transform = 'translateX(0)';

        setActiveLink();
    </script>

    @stack('scripts')
</body>

</html>
