@extends('layout.app')

@section('content')
<div class="bg-white rounded-3xl shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-plus-circle text-purple-600"></i> إعلان جديد
        </h2>
        <a href="{{ route('ads.index') }}" class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    {{-- رسالة الخطأ المحسنة --}}
    @if(session('error'))
    <div id="errorAlert" class="bg-red-50 border border-red-300 text-red-800 px-4 py-4 rounded-xl mb-6 shadow-md" role="alert">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <div class="bg-red-100 rounded-full p-2">
                    <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                </div>
            </div>
            <div class="flex-1">
                <h4 class="font-bold text-red-800 text-base mb-1"> لا يمكن إضافة إعلان جديد</h4>
                <p class="text-red-700 text-sm leading-relaxed">{{ session('error') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('errorAlert').remove()" class="flex-shrink-0 text-red-400 hover:text-red-600 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
    </div>

    <script>
        // اختفاء الرسالة تلقائياً بعد 5 ثوانٍ
        setTimeout(function() {
            let alertBox = document.getElementById('errorAlert');
            if (alertBox) {
                alertBox.style.transition = 'opacity 0.5s ease';
                alertBox.style.opacity = '0';
                setTimeout(function() {
                    alertBox.remove();
                }, 500);
            }
        }, 5000);
    </script>
    @endif

    {{-- رسالة النجاح --}}
    @if(session('success'))
    <div id="successAlert" class="bg-green-50 border border-green-300 text-green-800 px-4 py-4 rounded-xl mb-6 shadow-md" role="alert">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <div class="bg-green-100 rounded-full p-2">
                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                </div>
            </div>
            <div class="flex-1">
                <h4 class="font-bold text-green-800 text-base mb-1"> نجاح</h4>
                <p class="text-green-700 text-sm leading-relaxed">{{ session('success') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('successAlert').remove()" class="flex-shrink-0 text-green-400 hover:text-green-600 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
    </div>

    <script>
        setTimeout(function() {
            let alertBox = document.getElementById('successAlert');
            if (alertBox) {
                alertBox.style.transition = 'opacity 0.5s ease';
                alertBox.style.opacity = '0';
                setTimeout(function() {
                    alertBox.remove();
                }, 500);
            }
        }, 5000);
    </script>
    @endif

    <form action="{{ route('ads.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">تاريخ البدء (اختياري)</label>
                <input type="date" name="starts_at" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('starts_at') border-red-500 @enderror" value="{{ old('starts_at') }}">
                @error('starts_at')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">تاريخ الانتهاء (اختياري)</label>
                <input type="date" name="ends_at" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent @error('ends_at') border-red-500 @enderror" value="{{ old('ends_at') }}">
                @error('ends_at')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-gray-700 font-semibold mb-2">
                    الصور (يمكنك رفع أكثر من صورة)
                    <span class="text-red-500 text-sm">* مطلوب صورة واحدة على الأقل</span>
                </label>
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 hover:border-purple-400 transition">
                    <input type="file" name="images[]" multiple accept="image/*" class="w-full cursor-pointer" required>
                </div>
                <p class="text-sm text-gray-500 mt-2">يمكنك رفع حتى 10 صور، الحجم الأقصى 2MB لكل صورة</p>
                @error('images')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
                @error('images.*')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                    <span class="text-gray-700 font-semibold">تفعيل الإعلان فوراً</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 mr-7">إذا كان هناك إعلان نشط، لن يمكن تفعيل هذا الإعلان حتى انتهاء الإعلان الحالي</p>
            </div>
        </div>

        <div class="mt-8 flex gap-4">
        <button type="submit"
        style="background: linear-gradient(135deg, #7c3aed 0%, #db2777 100%); color: white; padding: 12px 32px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; margin-left: 10px; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);"
        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(124, 58, 237, 0.4)';"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(124, 58, 237, 0.3)';">
    <i class="fas fa-save"></i> حفظ الإعلان
</button>
            <a href="{{ route('ads.index') }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl hover:bg-gray-300 transition">
                <i class="fas fa-times ml-2"></i> إلغاء
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    // التحقق من صحة النموذج قبل الإرسال
    document.querySelector('form').addEventListener('submit', function(e) {
        const startsAt = document.querySelector('input[name="starts_at"]').value;
        const endsAt = document.querySelector('input[name="ends_at"]').value;

        if (startsAt && endsAt) {
            if (new Date(startsAt) > new Date(endsAt)) {
                e.preventDefault();
                alert(' تاريخ البدء لا يمكن أن يكون بعد تاريخ الانتهاء');
                return false;
            }
        }

        // التحقق من وجود صورة واحدة على الأقل
        const imagesInput = document.querySelector('input[name="images[]"]');
        if (!imagesInput.files || imagesInput.files.length === 0) {
            e.preventDefault();
            alert(' يجب رفع صورة واحدة على الأقل');
            return false;
        }

        // التحقق من حجم الصور
        for (let i = 0; i < imagesInput.files.length; i++) {
            const file = imagesInput.files[i];
            if (file.size > 2 * 1024 * 1024) {
                e.preventDefault();
                alert(` الصورة "${file.name}" حجمها يتجاوز 2 ميجابايت`);
                return false;
            }
        }
    });
</script>
@endpush
@endsection
