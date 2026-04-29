{{-- resources/views/admin/centers/index.blade.php --}}

@extends('layout.app')

@section('content')

<div id="centersDashboardView" class="page-transition"></div>

<!-- جدول الصالونات -->
<div class="bg-white rounded-3xl shadow-lg overflow-hidden border border-gray-100">

    <!-- Header -->

    <!-- فلترة البحث -->
    <div class="p-4 bg-gray-50 border-b border-gray-100">
        <form method="GET" action="{{ route('admin.center') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- البحث باسم الصالون -->
                <div class="relative">
                    <label class="block text-xs text-gray-500 mb-1">البحث باسم الصالون</label>
                    <div class="relative">
                        <i
                            class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="w-full pr-10 pl-3 py-2 border border-gray-200 rounded-xl focus:border-purple-400 focus:outline-none transition"
                            placeholder="ابحث باسم الصالون...">
                    </div>
                </div>

                <!-- فلترة حسب الحالة -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">الحالة</label>
                    <select name="status"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:border-purple-400 focus:outline-none transition">
                        <option value="">الكل</option>
                        <option value="active" {{ request('status')=='active' ? 'selected' : '' }}>نشط</option>
                        <option value="inactive" {{ request('status')=='inactive' ? 'selected' : '' }}>غير نشط</option>
                    </select>
                </div>

                <!-- ترتيب حسب التاريخ -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">الترتيب حسب التاريخ</label>
                    <select name="sort"
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl focus:border-purple-400 focus:outline-none transition">
                        <option value="desc" {{ request('sort')=='desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                        <option value="asc" {{ request('sort')=='asc' ? 'selected' : '' }}>الأقدم أولاً</option>
                    </select>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="flex items-end gap-4">
                    <div class="flex items-end gap-6">
                        <!-- زر بحث - أبيض -->
                        <button type="submit"
                            class="bg-white text-purple-700 border-2 border-purple-500 px-6 py-2.5 rounded-xl font-semibold hover:bg-purple-50 hover:shadow-lg transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-search"></i> بحث
                        </button>

                        <!-- زر إعادة تعيين - رمادي غامق -->
                        <a href="{{ route('admin.center') }}"
                            class="bg-gray-600 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-gray-700 transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-undo-alt"></i> إعادة تعيين
                        </a>

                        <!-- زر تحديث - أزرق غامق -->
                        <button type="button" onclick="refreshSalonsTable()"
                            class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-2.5 rounded-xl font-semibold hover:shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 flex items-center gap-2">
                            <i class="fas fa-sync-alt"></i> تحديث
                        </button>
                    </div>
                </div>
        </form>
    </div>

    <!-- Table -->
    <div class="p-4">
        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
            <table class="w-full text-center">
                <thead class="bg-gray-50 text-gray-600 text-sm">
                    <tr>
                        <th class="py-3 px-2">#</th>
                        <th class="py-3 px-2">اسم الصالون</th>
                        <th class="py-3 px-2">صاحب الصالون</th>
                        <th class="py-3 px-2">رقم الهاتف</th>
                        <th class="py-3 px-2">العنوان</th>
                        <th class="py-3 px-2">صور</th>
                        <th class="py-3 px-2">الحالة</th>
                        <th class="py-3 px-2">حلاقين</th>
                        <th class="py-3 px-2">تفاصيل</th>
                    </tr>
                </thead>
                <tbody id="salonsTableBody">
                    @forelse($salons as $index => $salon)
                    <tr class="border-b border-gray-100 hover:bg-purple-50 transition">
                        <td class="py-3 px-2">{{ ($salons->currentPage() - 1) * $salons->perPage() + $index + 1 }}</td>
                        <td class="py-3 px-2 font-semibold">
                            <i class="fas fa-store text-purple-500 ml-2"></i>
                            {{ $salon->name }}
                        </td>
                        <td class="py-3 px-2">
                            <div class="flex items-center justify-center gap-2">
                                <i class="fas fa-user-circle text-gray-400"></i>
                                {{ $salon->owner ? $salon->owner->name : 'غير معروف' }}
                            </div>
                        </td>
                        <td class="py-3 px-2 dir-ltr">{{ $salon->phone ?? '---' }}</td>
                        <td class="py-3 px-2 max-w-[200px] truncate" title="{{ $salon->address }}">
                            <i class="fas fa-location-dot text-gray-400 ml-1"></i>
                            {{ Str::limit($salon->address, 30) }}
                        </td>
                        <td class="py-3 px-2">
                            <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                                <i class="fas fa-image text-blue-500"></i>
                                {{ $salon->getMedia('salon_images')->count() }}
                            </span>
                        </td>
                        <td class="py-3 px-2">
                            @if($salon->is_active)
                            <span class="badge-status bg-green-100 text-green-700">
                                <i class="fas fa-circle-check ml-1 text-xs"></i> نشط
                            </span>
                            @else
                            <span class="badge-status bg-red-100 text-red-700">
                                <i class="fas fa-circle-exclamation ml-1 text-xs"></i> غير نشط
                            </span>
                            @endif
                        </td>
                        <td class="py-3 px-2">
                            <span class="inline-flex items-center gap-1">
                                <i class="fas fa-cut text-purple-500"></i>
                                {{ $salon->barbers()->count() }}
                            </span>
                        </td>
                        <td class="py-3 px-2">
                            <button onclick="openModal({{ $salon->id }})" class="btn-action btn-view"
                                title="عرض التفاصيل">
                                <i class="fas fa-eye"></i> عرض
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-8 text-center text-gray-400">
                            <i class="fas fa-store fa-3x mb-3 block text-gray-300"></i>
                            <p>لا يوجد صالونات حالياً</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $salons->links() }}
        </div>
    </div>
</div>

<!-- ========== موديل عرض التفاصيل (في منتصف الصفحة) ========== -->
<div id="salonModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-content">

            <!-- Header Modal -->
            <div class="modal-header">
                <div class="modal-header-content">
                    <div class="modal-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <h3 class="modal-title" id="modalSalonName">تفاصيل الصالون</h3>
                        <p class="modal-subtitle">إدارة الصالون والتحكم بحالته</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="modal-close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Body Modal -->
            <div class="modal-body">
                <!-- معلومات الصالون -->
                <div class="info-card">
                    <div class="card-header">
                        <div class="card-icon purple">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <h4 class="card-title">معلومات الصالون</h4>
                    </div>

                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">اسم الصالون:</span>
                            <span class="info-value" id="modalName">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">صاحب الصالون:</span>
                            <span class="info-value" id="modalOwner">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">رقم الهاتف:</span>
                            <span class="info-value dir-ltr" id="modalPhone">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">العنوان:</span>
                            <span class="info-value" id="modalAddress">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">عدد الحلاقين:</span>
                            <span class="info-value" id="modalBarbersCount">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">الحالة الحالية:</span>
                            <span id="currentStatusBadge"></span>
                        </div>
                    </div>
                </div>

               
                <!-- أزرار الإجراءات -->
                <div class="actions-card">
                    <div class="card-header">
                        <div class="card-icon pink">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <h4 class="card-title">الإجراءات المتاحة</h4>
                    </div>

                    <div class="card-body">
                        <!-- زر تغيير الحالة -->
                        <form action="" method="POST" id="toggleStatusForm">
                            @csrf
                            @method('PUT')
                            <button type="submit" id="toggleStatusBtn" class="action-btn">
                                <i class="fas" id="toggleIcon"></i>
                                <span id="toggleText"></span>
                            </button>
                        </form>

                        <!-- مسافة بين الزرين -->
                        <div style="height: 12px;"></div>

                        <!-- زر الحذف -->
                        <button type="button" onclick="showDeleteConfirmation()" class="action-btn delete-btn">
                            <i class="fas fa-trash-alt"></i>
                            حذف الصالون
                        </button>

                        <!-- مسافة بين الزرين -->
                        <div style="height: 12px;"></div>

                        <!-- زر عرض التفاصيل الكاملة -->
                        <a href="#" id="viewDetailsLink" class="action-btn details-btn">
                            <i class="fas fa-external-link-alt"></i>
                            عرض التفاصيل الكاملة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== رسالة تأكيد الحذف ========== -->
<div id="deleteConfirmation" class="delete-confirmation" style="display: none;">
    <div class="delete-confirmation-content">
        <div class="delete-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h4 class="delete-title">تأكيد الحذف</h4>
        <p class="delete-message">هل أنت متأكد من حذف هذا الصالون نهائياً؟</p>
        <p class="delete-warning">
            سيتم حذف جميع بيانات الصالون بما فيها:<br>
            • الحلاقين<br>
            • الصور<br>
            • الحجوزات<br>
            • التقييمات
        </p>
        <div class="delete-actions">
            <button onclick="confirmDeleteAction()" class="delete-confirm-btn">
                <i class="fas fa-check"></i> نعم، احذف
            </button>
            <button onclick="cancelDelete()" class="delete-cancel-btn">
                <i class="fas fa-times"></i> إلغاء
            </button>
        </div>
    </div>
</div>

<style>
    /* Badge styles */
    .badge-status {
        padding: 5px 12px;
        border-radius: 60px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-action {
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
        padding: 6px 12px;
        border-radius: 8px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
    }

    .btn-view {
        color: #3498db;
        background: #e8f4ff;
    }

    .btn-view:hover {
        background: #d1ecff;
        transform: scale(1.02);
    }

    .dir-ltr {
        direction: ltr;
        display: inline-block;
    }

    .truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }

    /* Pagination styles */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
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

    /* ========== Modal Styles (Centered) ========== */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-container {
        width: 100%;
        max-width: 500px;
        margin: 1rem;
        animation: modalFadeIn 0.2s ease-out;
    }

    .modal-content {
        background: white;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    /* Modal Header */
    .modal-header {
        background: linear-gradient(135deg, #6c5ce7, #a855f7);
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .modal-icon {
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-icon i {
        font-size: 1.5rem;
        color: white;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 800;
        color: white;
        margin: 0;
    }

    .modal-subtitle {
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.8);
        margin-top: 0.2rem;
    }

    .modal-close-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        color: white;
    }

    .modal-close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }

    /* Modal Body */
    .modal-body {
        padding: 1.5rem;
        max-height: 70vh;
        overflow-y: auto;
    }

    /* Cards inside modal */
    .info-card,
    .actions-card {
        background: #f8fafc;
        border-radius: 20px;
        margin-bottom: 1.25rem;
        overflow: hidden;
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .card-icon {
        width: 28px;
        height: 28px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .card-icon.purple {
        background: #e9d5ff;
        color: #7c3aed;
    }

    .card-icon.pink {
        background: #fce7f3;
        color: #db2777;
    }

    .card-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .card-body {
        padding: 1rem;
    }

    /* Info rows */
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px dashed #e2e8f0;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 0.75rem;
        color: #64748b;
    }

    .info-value {
        font-size: 0.85rem;
        font-weight: 600;
        color: #1e293b;
    }

    /* Action buttons */
    .action-btn {
        width: 100%;
        padding: 0.7rem 1rem;
        border-radius: 14px;
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        margin-bottom: 0.75rem;
        text-decoration: none;
    }

    .action-btn:last-child {
        margin-bottom: 0;
    }

    .action-btn#toggleStatusBtn {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .action-btn#toggleStatusBtn i {
        color: white;
    }

    .details-btn {
        background: linear-gradient(135deg, #8b5cf6, #d946ef);
        color: white;
    }

    .details-btn:hover,
    .action-btn#toggleStatusBtn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .action-btn.delete-btn {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }

    .action-btn.delete-btn:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
    }

    /* Animation */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Scrollbar */
    .modal-body::-webkit-scrollbar {
        width: 5px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: #c084fc;
        border-radius: 10px;
    }

    /* ========== Delete Confirmation Modal Styles ========== */
    .delete-confirmation {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(4px);
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .delete-confirmation-content {
        background: white;
        border-radius: 28px;
        max-width: 400px;
        width: 90%;
        padding: 1.8rem;
        text-align: center;
        animation: modalFadeIn 0.2s ease-out;
    }

    .delete-icon {
        width: 70px;
        height: 70px;
        background: #fee2e2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
    }

    .delete-icon i {
        font-size: 2.5rem;
        color: #dc2626;
    }

    .delete-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .delete-message {
        color: #475569;
        font-size: 0.95rem;
        margin-bottom: 1rem;
    }

    .delete-warning {
        background: #fef3c7;
        color: #b45309;
        padding: 0.8rem;
        border-radius: 16px;
        font-size: 0.75rem;
        margin-bottom: 1.5rem;
        text-align: right;
        line-height: 1.6;
    }

    .delete-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .delete-confirm-btn {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 40px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .delete-confirm-btn:hover {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        transform: scale(1.02);
    }

    .delete-cancel-btn {
        background: #e2e8f0;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 40px;
        color: #475569;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .delete-cancel-btn:hover {
        background: #cbd5e1;
        transform: scale(1.02);
    }
</style>

<script>
    let currentSalonId = null;
    let currentSalonStatus = null;

    // فتح الموديل
    function openModal(salonId) {
        currentSalonId = salonId;

        // عرض loading
        document.getElementById('modalSalonName').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحميل...';
        document.getElementById('salonModal').style.display = 'flex';

        // إرسال طلب AJAX لجلب البيانات
        fetch(`/admin/centers/${salonId}/json`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const salon = data.salon;
                currentSalonStatus = salon.is_active;

                // تعبئة البيانات
                document.getElementById('modalSalonName').innerText = salon.name;
                document.getElementById('modalName').innerText = salon.name;
                document.getElementById('modalOwner').innerText = salon.owner?.name || 'غير معروف';
                document.getElementById('modalPhone').innerText = salon.phone || '---';
                document.getElementById('modalAddress').innerText = salon.address || 'غير محدد';
                document.getElementById('modalBarbersCount').innerText = salon.barbers_count || 0;

                // تحديث حالة الصالون
                updateStatusBadge(salon.is_active);

                // تحديث زر تغيير الحالة
                updateToggleButton(salon.is_active, salonId);

                // تحديث رابط عرض التفاصيل
                const viewLink = document.getElementById('viewDetailsLink');
                viewLink.href = `/admin/centers/${salonId}`;
            } else {
                alert('حدث خطأ في تحميل البيانات');
                closeModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في تحميل البيانات');
            closeModal();
        });
    }

    // عرض رسالة تأكيد الحذف
    function showDeleteConfirmation() {
        if (currentSalonId) {
            document.getElementById('deleteConfirmation').style.display = 'flex';
        }
    }

    // تأكيد الحذف
    function confirmDeleteAction() {
        if (!currentSalonId) return;

        fetch(`/admin/centers/${currentSalonId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إخفاء رسالة التأكيد والموديل
                cancelDelete();
                closeModal();
                // إعادة تحميل الصفحة
                location.reload();
            } else {
                alert('حدث خطأ أثناء حذف الصالون: ' + (data.message || 'خطأ غير معروف'));
                cancelDelete();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم');
            cancelDelete();
        });
    }

    // إلغاء الحذف
    function cancelDelete() {
        document.getElementById('deleteConfirmation').style.display = 'none';
    }

    // تحديث عرض حالة الصالون
    function updateStatusBadge(isActive) {
        const badgeContainer = document.getElementById('currentStatusBadge');
        if (isActive) {
            badgeContainer.innerHTML = '<span class="badge-status bg-green-100 text-green-700"><i class="fas fa-circle-check ml-1 text-xs"></i> نشط</span>';
        } else {
            badgeContainer.innerHTML = '<span class="badge-status bg-red-100 text-red-700"><i class="fas fa-circle-exclamation ml-1 text-xs"></i> غير نشط</span>';
        }
    }

    // تحديث زر تغيير الحالة
    function updateToggleButton(isActive, salonId) {
        const toggleBtn = document.getElementById('toggleStatusBtn');
        const toggleIcon = document.getElementById('toggleIcon');
        const toggleText = document.getElementById('toggleText');
        const toggleForm = document.getElementById('toggleStatusForm');

        if (isActive) {
            toggleBtn.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            toggleIcon.className = 'fas fa-ban';
            toggleText.innerText = 'إيقاف الصالون';
            toggleForm.action = `/admin/centers/${salonId}/deactivate`;
        } else {
            toggleBtn.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
            toggleIcon.className = 'fas fa-play';
            toggleText.innerText = 'تفعيل الصالون';
            toggleForm.action = `/admin/centers/${salonId}/activate`;
        }
    }

    // إغلاق الموديل
    function closeModal() {
        document.getElementById('salonModal').style.display = 'none';
        currentSalonId = null;
    }

    // إغلاق الموديل عند الضغط على ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('deleteConfirmation').style.display === 'flex') {
                cancelDelete();
            } else if (document.getElementById('salonModal').style.display === 'flex') {
                closeModal();
            }
        }
    });

    // إغلاق الموديل عند النقر خارج المحتوى
    document.getElementById('salonModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // إغلاق رسالة التأكيد عند النقر خارج المحتوى
    document.getElementById('deleteConfirmation').addEventListener('click', function(e) {
        if (e.target === this) {
            cancelDelete();
        }
    });
</script>

<script>
    let currentPage = 1;
    let currentSearch = '';
    let currentStatus = '';
    let currentSort = 'desc';

    // حفظ قيم الفلترة الحالية
    function saveFilterValues() {
        currentSearch = document.querySelector('input[name="search"]')?.value || '';
        currentStatus = document.querySelector('select[name="status"]')?.value || '';
        currentSort = document.querySelector('select[name="sort"]')?.value || 'desc';
        currentPage = document.querySelector('.pagination .active .page-link')?.innerText || 1;
    }

    // تحديث الجدول بالكامل
    function refreshSalonsTable() {
        saveFilterValues();

        // إظهار مؤشر التحميل
        const tbody = document.getElementById('salonsTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="py-8 text-center">
                        <i class="fas fa-spinner fa-spin text-purple-500 text-2xl"></i>
                        <p class="text-gray-400 mt-2">جاري تحديث البيانات...</p>
                    </td>
                </tr>
            `;
        }

        // إرسال طلب AJAX
        fetch(`{{ route('admin.center') }}?search=${encodeURIComponent(currentSearch)}&status=${currentStatus}&sort=${currentSort}&page=${currentPage}&ajax=1`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // تحديث محتوى الجدول
                if (data.html) {
                    tbody.innerHTML = data.html;
                }

                // تحديث روابط التصفح
                if (data.pagination) {
                    const paginationContainer = document.querySelector('.mt-4');
                    if (paginationContainer) {
                        paginationContainer.innerHTML = data.pagination;
                    }
                }

                // عرض رسالة نجاح
                showToast('تم تحديث البيانات بنجاح', 'success');
            } else {
                showToast('حدث خطأ في تحديث البيانات', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('حدث خطأ في الاتصال بالخادم', 'error');
        });
    }

    // دالة عرض رسالة منبثقة
    function showToast(message, type = 'success') {
        // إنشاء عنصر التوست
        const toast = document.createElement('div');
        toast.className = `fixed bottom-5 left-5 z-50 px-4 py-2 rounded-lg text-white shadow-lg transition-all duration-300 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} ml-2"></i> ${message}`;
        document.body.appendChild(toast);

        // إخفاء التوست بعد 3 ثوانٍ
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // تحديث تلقائي كل 30 ثانية
    let autoRefreshInterval = null;

    function startAutoRefresh() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        autoRefreshInterval = setInterval(() => {
            refreshSalonsTable();
        }, 30000); // 30 ثانية
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }

    // بدء التحديث التلقائي عند تحميل الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        startAutoRefresh();
    });

    // إيقاف التحديث عند مغادرة الصفحة
    window.addEventListener('beforeunload', function() {
        stopAutoRefresh();
    });

    // إضافة مستمع لأزرار التصفح بعد تحميلها
    document.addEventListener('click', function(e) {
        if (e.target.closest('.pagination a')) {
            e.preventDefault();
            const url = e.target.closest('.pagination a').getAttribute('href');
            if (url) {
                const pageMatch = url.match(/[?&]page=(\d+)/);
                if (pageMatch) {
                    currentPage = pageMatch[1];
                    refreshSalonsTable();
                }
            }
        }
    });

    // تحديث الفلترة عند الضغط على زر البحث
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveFilterValues();
            currentPage = 1;
            refreshSalonsTable();
        });
    }
</script>


@endsection
