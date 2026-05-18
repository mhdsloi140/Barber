{{-- resources/views/admin/dashboard.blade.php --}}

@extends('layout.app')

@section('content')
<div id="dashboardView" class="page-transition">

    <!-- بطاقات الإحصائيات الرئيسية -->
    <div class="stats-grid">

        {{-- إجمالي الإيرادات --}}
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-slate-500 text-sm font-medium">إجمالي الإيرادات</p>
                    <h3 class="text-3xl font-extrabold text-gray-800 mt-1">
                        {{ number_format($revenueStats['total'], 2) }} ₪
                    </h3>
                    @if($revenueChange != 0)
                    <span class="{{ $revenueChange >= 0 ? 'text-emerald-500' : 'text-rose-500' }} text-xs bg-emerald-50 px-2 py-0.5 rounded-full">
                        {{ $revenueChange >= 0 ? '+' : '' }}{{ $revenueChange }}%
                    </span>
                    @endif
                </div>
                <div class="icon-bg-grad"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>

        {{-- إجمالي المستخدمين مع تفاصيل --}}
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div class="w-full">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500 text-sm font-medium">إجمالي المستخدمين</p>
                            <h3 class="text-3xl font-extrabold text-gray-800 mt-1">{{ number_format($usersStats['total']) }}</h3>
                        </div>
                        <div class="icon-bg-grad"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-gray-100">
                        <div class="flex justify-between text-sm"><span class="text-gray-500">نشط</span><span class="text-green-600 font-semibold">{{ number_format($usersStats['active']) }}</span></div>
                        <div class="flex justify-between text-sm mt-1"><span class="text-gray-500">غير نشط</span><span class="text-red-600 font-semibold">{{ number_format($usersStats['inactive']) }}</span></div>
                        <div class="flex justify-between text-sm mt-1"><span class="text-gray-500">عملاء</span><span>{{ number_format($usersStats['customers']) }}</span></div>
                        <div class="flex justify-between text-sm mt-1"><span class="text-gray-500">حلاقين</span><span>{{ number_format($usersStats['barbers']) }}</span></div>
                        <div class="flex justify-between text-sm mt-1"><span class="text-gray-500">مالكي صالونات</span><span>{{ number_format($usersStats['salon_owners']) }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- الحجوزات المكتملة --}}
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div class="w-full">
                    <div class="flex justify-between items-start">
                        <div><p class="text-slate-500 text-sm font-medium">الحجوزات المكتملة</p><h3 class="text-3xl font-extrabold text-gray-800 mt-1">{{ number_format($appointmentsStats['completed']) }}</h3></div>
                        <div class="icon-bg-grad" style="background: linear-gradient(125deg, #10b981, #059669);"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-gray-100">
                        <div class="flex justify-between items-center"><span class="text-gray-500 text-sm">من إجمالي {{ number_format($appointmentsStats['total']) }} حجز</span><span class="text-green-600 font-bold text-lg">{{ $appointmentsStats['total'] > 0 ? round(($appointmentsStats['completed'] / $appointmentsStats['total']) * 100, 1) : 0 }}%</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2"><div class="bg-green-500 h-2 rounded-full" style="width: {{ $appointmentsStats['total'] > 0 ? ($appointmentsStats['completed'] / $appointmentsStats['total']) * 100 : 0 }}%"></div></div>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-calendar-check ml-1"></i> {{ $appointmentsStats['completed'] }} حجز مكتمل</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- الحجوزات الملغية --}}
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div class="w-full">
                    <div class="flex justify-between items-start">
                        <div><p class="text-slate-500 text-sm font-medium">الحجوزات الملغية</p><h3 class="text-3xl font-extrabold text-gray-800 mt-1">{{ number_format($appointmentsStats['cancelled']) }}</h3></div>
                        <div class="icon-bg-grad" style="background: linear-gradient(125deg, #ef4444, #dc2626);"><i class="fas fa-ban"></i></div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-gray-100">
                        <div class="flex justify-between items-center"><span class="text-gray-500 text-sm">من إجمالي {{ number_format($appointmentsStats['total']) }} حجز</span><span class="text-red-600 font-bold text-lg">{{ $appointmentsStats['total'] > 0 ? round(($appointmentsStats['cancelled'] / $appointmentsStats['total']) * 100, 1) : 0 }}%</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2"><div class="bg-red-500 h-2 rounded-full" style="width: {{ $appointmentsStats['total'] > 0 ? ($appointmentsStats['cancelled'] / $appointmentsStats['total']) * 100 : 0 }}%"></div></div>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-calendar-times ml-1"></i> {{ $appointmentsStats['cancelled'] }} حجز ملغي</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- الصالونات النشطة --}}
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div class="w-full">
                    <div class="flex justify-between items-start"><div><p class="text-slate-500 text-sm font-medium">الصالونات النشطة</p><h3 class="text-3xl font-extrabold text-gray-800 mt-1">{{ number_format($salonsStats['active']) }}</h3></div><div class="icon-bg-grad" style="background: linear-gradient(125deg, #10b981, #059669);"><i class="fas fa-check-circle"></i></div></div>
                    <div class="mt-3 pt-2 border-t border-gray-100">
                        <div class="flex justify-between items-center"><span class="text-gray-500 text-sm">من إجمالي {{ number_format($salonsStats['total']) }} صالون</span><span class="text-green-600 font-bold text-lg">{{ $salonsStats['total'] > 0 ? round(($salonsStats['active'] / $salonsStats['total']) * 100, 1) : 0 }}%</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2"><div class="bg-green-500 h-2 rounded-full" style="width: {{ $salonsStats['total'] > 0 ? ($salonsStats['active'] / $salonsStats['total']) * 100 : 0 }}%"></div></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- صالونات قيد المراجعة --}}
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div class="w-full">
                    <div class="flex justify-between items-start"><div><p class="text-slate-500 text-sm font-medium">🕒 صالونات قيد المراجعة</p><h3 class="text-3xl font-extrabold text-gray-800 mt-1">{{ number_format($salonsStats['inactive']) }}</h3></div><div class="icon-bg-grad" style="background: linear-gradient(125deg, #f59e0b, #d97706);"><i class="fas fa-clock"></i></div></div>
                    <div class="mt-3 pt-2 border-t border-gray-100">
                        <div class="flex justify-between items-center"><span class="text-gray-500 text-sm">من إجمالي {{ number_format($salonsStats['total']) }} صالون</span><span class="text-amber-600 font-bold text-lg">{{ $salonsStats['total'] > 0 ? round(($salonsStats['inactive'] / $salonsStats['total']) * 100, 1) : 0 }}%</span></div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2"><div class="bg-amber-500 h-2 rounded-full" style="width: {{ $salonsStats['total'] > 0 ? ($salonsStats['inactive'] / $salonsStats['total']) * 100 : 0 }}%"></div></div>
                        <div class="mt-3"><a href="{{ route('admin.center') }}" class="text-xs text-amber-600 hover:text-amber-700 transition flex items-center gap-1"><i class="fas fa-eye"></i> عرض الصالونات غير المفعلة</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; border-radius: 28px; padding: 1.3rem; transition: all 0.25s; box-shadow: 0 10px 20px rgba(0,0,0,0.03), 0 6px 6px rgba(0,0,0,0.05); border: 1px solid rgba(128, 90, 213, 0.1); }
    .icon-bg-grad { background: linear-gradient(125deg, #8b5cf6, #ec4899); width: 54px; height: 54px; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8rem; box-shadow: 0 8px 14px rgba(139, 92, 246, 0.3); }
</style>
@endsection

@push('scripts')
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>

<script>
// تأكد من أن Firebase لم يتم تهيئته مسبقاً
if (typeof firebase !== 'undefined' && firebase.apps && !firebase.apps.length) {
    const firebaseConfig = {
        apiKey: "AIzaSyA_if2fnykQlUH5RumzFcAiday7qaxnoV0",
        authDomain: "naemen-57c3f.firebaseapp.com",
        projectId: "naemen-57c3f",
        storageBucket: "naemen-57c3f.firebasestorage.app",
        messagingSenderId: "125209052652",
        appId: "1:125209052652:web:79e6cdc684101844ec6cc9"
    };
    firebase.initializeApp(firebaseConfig);
    console.log(' Firebase initialized');
}

const messaging = firebase.messaging();

async function initFirebase() {
    try {
        // طلب إذن الإشعارات
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.log(' تم رفض الإشعارات');
            return;
        }
        console.log(' تم السماح بالإشعارات');

        // الحصول على التوكن
        const token = await messaging.getToken({
            vapidKey: 'BOstcsJqQUt2FAnPQyCEOv2HoaRTGnyyjLT1ShBubbCh5kYqyxyopWwskNudFswEZhhoOKO7YgMZLT5eCFViCEs'
        });

        if (token) {
            console.log('📱 FCM Token:', token.substring(0, 50) + '...');

            // إرسال التوكن إلى الخادم
            const response = await fetch('/fcm-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ fcm_token: token })
            });

            const data = await response.json();
            if (data.success) {
                console.log(' تم حفظ التوكن في الخادم');
            } else {
                console.log(' فشل حفظ التوكن');
            }
        } else {
            console.log(' لم يتم الحصول على التوكن');
        }
    } catch (error) {
        console.error(' خطأ في Firebase:', error);
    }
}

// بدء العملية
if (Notification.permission === 'default') {
    initFirebase();
} else if (Notification.permission === 'granted') {
    initFirebase();
} else {
    console.log(' الإشعارات مرفوضة مسبقاً');
}
</script>

<script>
    // تحديث الصفحة تلقائياً كل 30 ثانية
    let refreshInterval = null;

    function startAutoRefresh() {
        if (refreshInterval) clearInterval(refreshInterval);
        refreshInterval = setInterval(() => refreshDashboard(), 30000);
    }

    function refreshDashboard() {
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach(card => card.style.opacity = '0.5');

        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newStatsGrid = doc.querySelector('.stats-grid');
            if (newStatsGrid) document.querySelector('.stats-grid').innerHTML = newStatsGrid.innerHTML;
            cards.forEach(card => card.style.opacity = '1');
        })
        .catch(error => {
            console.error('Error refreshing dashboard:', error);
            cards.forEach(card => card.style.opacity = '1');
        });
    }

    document.addEventListener('DOMContentLoaded', () => startAutoRefresh());
    window.addEventListener('beforeunload', () => { if (refreshInterval) clearInterval(refreshInterval); });

    setTimeout(() => {
        const statsGrid = document.querySelector('.stats-grid');
        if (statsGrid && !document.querySelector('.refresh-btn-container')) {
            const refreshBtn = document.createElement('div');
            refreshBtn.className = 'refresh-btn-container mb-4 flex justify-end';
            refreshBtn.innerHTML = `<button onclick="refreshDashboard()" class="bg-white text-purple-600 border-2 border-purple-500 px-4 py-2 rounded-xl hover:bg-purple-50 hover:shadow-lg transition flex items-center gap-2 text-sm font-semibold"><i class="fas fa-sync-alt text-purple-500"></i> تحديث البيانات الآن</button>`;
            statsGrid.parentNode.insertBefore(refreshBtn, statsGrid);
        }
    }, 500);
</script>
@endpush
