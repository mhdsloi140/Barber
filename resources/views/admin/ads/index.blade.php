@extends('layout.app')

@section('content')
<div class="bg-white rounded-3xl shadow-md p-6">
  <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
    <h2 class="text-2xl font-bold text-gray-800">
        <i class="fas fa-bullhorn text-purple-600 ml-2"></i> إدارة الإعلانات
    </h2>
    <a href="{{ route('ads.create') }}"
       style="background-color: #7c3aed; color: white;"
       class="px-5 py-2.5 rounded-xl hover:bg-purple-700 transition flex items-center gap-2 shadow-md">
        <i class="fas fa-plus"></i> إعلان جديد
    </a>
</div>

    @if(session('success'))
        <div class="bg-green-50 border-r-4 border-green-500 text-green-700 px-4 py-3 rounded-xl mb-4 shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border-r-4 border-red-500 text-red-700 px-4 py-3 rounded-xl mb-4 shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-gray-100">
        <table class="w-full text-center">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-2 text-gray-600 font-semibold text-sm">#</th>
                    <th class="py-3 px-2 text-gray-600 font-semibold text-sm">الصور</th>
                    <th class="py-3 px-2 text-gray-600 font-semibold text-sm">الفترة</th>
                    <th class="py-3 px-2 text-gray-600 font-semibold text-sm">الحالة</th>
                    <th class="py-3 px-2 text-gray-600 font-semibold text-sm">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ads as $ad)
                <tr class="border-b border-gray-100 hover:bg-purple-50 transition">
                    <td class="py-3 px-2 text-gray-700">{{ $ad->id }}</td>
                    <td class="py-3 px-2">
                        <div class="flex gap-1 justify-center">
                            @forelse($ad->images as $index => $image)
                                @if($index < 3)
                                    <img src="{{ $image->getUrl('thumb') }}" class="w-10 h-10 object-cover rounded-lg border border-gray-200 shadow-sm">
                                @endif
                            @empty
                                <span class="text-gray-400 text-sm">لا توجد صور</span>
                            @endforelse
                            @if($ad->images->count() > 3)
                                <span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2 py-1">+{{ $ad->images->count() - 3 }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="py-3 px-2 text-sm">
                        @if($ad->starts_at || $ad->ends_at)
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-gray-700">{{ $ad->starts_at ? $ad->starts_at->format('Y/m/d') : '---' }}</span>
                                <i class="fas fa-arrow-left text-gray-400 text-xs"></i>
                                <span class="text-gray-700">{{ $ad->ends_at ? $ad->ends_at->format('Y/m/d') : '---' }}</span>
                            </div>
                        @else
                            <span class="text-blue-600 font-semibold">دائم</span>
                        @endif
                    </td>
                    <td class="py-3 px-2">
                        <button onclick="toggleStatus({{ $ad->id }})"
                            class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 {{ $ad->is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-rose-100 text-rose-700 hover:bg-rose-200' }}">
                            <i class="fas {{ $ad->is_active ? 'fa-check-circle' : 'fa-times-circle' }} ml-1"></i>
                            {{ $ad->is_active ? 'نشط' : 'غير نشط' }}
                        </button>
                    </td>
                    <td class="py-3 px-2">
                        <div class="flex gap-2 justify-center">
                            <a href="{{ route('ads.edit', $ad) }}"
                               class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 hover:scale-105"
                               title="تعديل">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="showDeleteConfirmation({{ $ad->id }})"
                                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200 bg-rose-50 text-rose-600 hover:bg-rose-100 hover:scale-105"
                                    title="حذف">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <form action="{{ route('ads.duplicate', $ad) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-all duration-200 bg-gray-100 text-gray-600 hover:bg-gray-200 hover:scale-105"
                                        title="نسخ"
                                        onclick="return confirm('هل أنت متأكد من نسخ هذا الإعلان؟')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-12 text-center">
                        <i class="fas fa-bullhorn text-5xl text-gray-300 mb-3 block"></i>
                        <p class="text-gray-400 text-lg">لا توجد إعلانات حتى الآن</p>
                        <a href="{{ route('ads.create') }}" class="inline-block mt-3 text-purple-600 hover:text-purple-700 font-semibold">
                            <i class="fas fa-plus ml-1"></i> أضف أول إعلان
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($ads->hasPages())
        <div class="mt-6">
            {{ $ads->links() }}
        </div>
    @endif
</div>

<!-- ========== موديل تأكيد حذف الإعلان ========== -->
<div id="deleteConfirmation" class="delete-confirmation" style="display: none;">
    <div class="delete-confirmation-content">
        <div class="delete-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h4 class="delete-title">تأكيد الحذف</h4>
        <p class="delete-message">هل أنت متأكد من حذف هذا الإعلان نهائياً؟</p>
        <div class="delete-warning">
            <i class="fas fa-exclamation-circle ml-2"></i>
            سيتم حذف جميع بيانات الإعلان بما فيها:
            <ul class="mt-2 list-disc list-inside text-right">
                <li>الصور المرفقة</li>
                <li>جميع بيانات الإعلان</li>
            </ul>
        </div>
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
    /* تنسيق الجدول */
    .custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    /* تنسيق الأزرار */
    .btn-action {
        transition: all 0.2s ease;
    }

    /* تنسيق الترقيم */
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
        border-radius: 10px;
        background: #f3f4f6;
        color: #4b5563;
        text-decoration: none;
        transition: all 0.2s;
        font-size: 14px;
    }

    .pagination .page-link:hover {
        background: #7c3aed;
        color: white;
    }

    .pagination .active .page-link {
        background: #7c3aed;
        color: white;
    }

    /* ========== موديل الحذف ========== */
    .delete-confirmation {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(4px);
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.2s ease-out;
    }

    .delete-confirmation-content {
        background: white;
        border-radius: 28px;
        max-width: 420px;
        width: 90%;
        padding: 2rem;
        text-align: center;
        animation: slideUp 0.3s ease-out;
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
        padding: 1rem;
        border-radius: 16px;
        font-size: 0.8rem;
        margin-bottom: 1.5rem;
        text-align: right;
    }

    .delete-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }

    .delete-confirm-btn {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        padding: 0.6rem 1.5rem;
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
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }

    .delete-cancel-btn {
        background: #e2e8f0;
        border: none;
        padding: 0.6rem 1.5rem;
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

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    let currentAdId = null;

    function showDeleteConfirmation(adId) {
        currentAdId = adId;
        document.getElementById('deleteConfirmation').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function confirmDeleteAction() {
        if (!currentAdId) return;

        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        formData.append('_method', 'DELETE');

        fetch(`/ads/${currentAdId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cancelDelete();
                location.reload();
            } else {
                alert('حدث خطأ أثناء حذف الإعلان: ' + (data.message || 'خطأ غير معروف'));
                cancelDelete();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم');
            cancelDelete();
        });
    }

    function cancelDelete() {
        document.getElementById('deleteConfirmation').style.display = 'none';
        document.body.style.overflow = '';
        currentAdId = null;
    }

    function toggleStatus(adId) {
        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch(`/ads/${adId}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء تغيير الحالة');
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('deleteConfirmation').style.display === 'flex') {
                cancelDelete();
            }
        }
    });

    document.getElementById('deleteConfirmation').addEventListener('click', function(e) {
        if (e.target === this) {
            cancelDelete();
        }
    });
</script>

@endsection
