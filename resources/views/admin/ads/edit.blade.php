@extends('layout.app')

@section('content')
<div class="bg-white rounded-3xl shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-edit text-purple-600"></i> تعديل الإعلان
        </h2>
        <a href="{{ route('ads.index') }}" class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-arrow-right"></i> رجوع
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

    <form action="{{ route('ads.update', $advertisement) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">تاريخ البدء (اختياري)</label>
                <input type="date" name="starts_at"
                    class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                    value="{{ old('starts_at', optional($advertisement->starts_at)->format('Y-m-d')) }}">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">تاريخ الانتهاء (اختياري)</label>
                <input type="date" name="ends_at"
                    class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500"
                    value="{{ old('ends_at', optional($advertisement->ends_at)->format('Y-m-d')) }}">
            </div>

            @if($images->count() > 0)
            <div class="md:col-span-2">
                <label class="block text-gray-700 font-semibold mb-3">الصور الحالية</label>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    @foreach($images as $image)
                    <div class="image-card relative group" data-image-id="{{ $image->id }}">
                        <div
                            class="relative border-2 rounded-xl overflow-hidden bg-gray-100 border-gray-200 image-container">
                            <img src="{{ $image->getUrl('medium') }}" class="w-full h-40 object-cover cursor-pointer"
                                onclick="toggleSelectImage({{ $image->id }})">

                            {{-- زر الحذف --}}
                            <button type="button" onclick="toggleSelectImage({{ $image->id }})"
                                class="absolute top-2 right-2 w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-200 hover:bg-red-600">
                                <i class="fas fa-trash text-sm"></i>
                            </button>

                            {{-- علامة تحديد --}}
                            <div
                                class="absolute top-2 left-2 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center hidden selection-check">
                                <i class="fas fa-check text-white text-xs"></i>
                            </div>
                        </div>

                        <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" id="img_{{ $image->id }}"
                            class="hidden delete-checkbox">

                        <p class="text-center text-xs text-gray-500 mt-2">صورة {{ $loop->iteration }}</p>
                    </div>
                    @endforeach
                </div>

                <div class="mt-3 p-3 bg-blue-50 rounded-xl">
                    <p class="text-sm text-blue-700 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        <span>لحذف صورة: اضغط على الصورة أو زر الحذف الأحمر الموجود فوقها</span>
                    </p>
                </div>
            </div>
            @endif

            <div class="md:col-span-2">
                <label class="block text-gray-700 font-semibold mb-2">إضافة صور جديدة (اختياري)</label>
                <div
                    class="border-2 border-dashed border-gray-300 rounded-xl p-6 hover:border-purple-400 transition text-center">
                    <input type="file" name="images[]" multiple accept="image/*" class="w-full cursor-pointer"
                        id="newImages" onchange="previewNewImages(this)">
                    <p class="text-sm text-gray-500 mt-2">
                        <i class="fas fa-cloud-upload-alt ml-1"></i>
                        يمكنك رفع حتى 10 صور، الحجم الأقصى 2MB لكل صورة
                    </p>
                </div>

                {{-- معاينة الصور الجديدة --}}
                <div id="newImagesPreview" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4"></div>

                @error('images')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
                @error('images.*')<p class="text-red-500 text-sm mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-xl">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $advertisement->is_active) ?
                    'checked' : '' }}
                    class="w-5 h-5 text-purple-600 rounded focus:ring-purple-500">
                    <span class="text-gray-700 font-semibold">تفعيل الإعلان</span>
                </label>
            </div>
        </div>

        <div class="mt-8 flex gap-4">
            <button type="submit"
                style="background-color: #7c3aed; color: white; padding: 12px 32px; border-radius: 12px; border: none; cursor: pointer; font-weight: 600; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"
                onmouseover="this.style.backgroundColor='#6d28d9'; this.style.transform='scale(1.05)'; this.style.boxShadow='0 8px 15px rgba(0,0,0,0.2)';"
                onmouseout="this.style.backgroundColor='#7c3aed'; this.style.transform='scale(1)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';">
                <i class="fas fa-save"></i> تحديث الإعلان
            </button>
            <a href="{{ route('ads.index') }}"
                class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl hover:bg-gray-300 transition">
                <i class="fas fa-times ml-2"></i> إلغاء
            </a>
        </div>
    </form>
</div>

<style>
    .image-card {
        transition: all 0.2s ease;
    }

    .image-card.selected .image-container {
        border-color: #ef4444 !important;
        opacity: 0.6;
    }

    .image-card.selected .selection-check {
        display: flex !important;
    }

    .image-container {
        transition: all 0.2s ease;
    }

    .group:hover .group-hover\:opacity-100 {
        opacity: 1;
    }
</style>

<script>
    // دالة تبديل تحديد الصورة للحذف
    function toggleSelectImage(imageId) {
        const imageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
        const checkbox = document.getElementById(`img_${imageId}`);

        if (imageCard.classList.contains('selected')) {
            // إلغاء التحديد
            imageCard.classList.remove('selected');
            checkbox.checked = false;
        } else {
            // تحديد الصورة
            imageCard.classList.add('selected');
            checkbox.checked = true;
        }
    }

    // معاينة الصور الجديدة
    function previewNewImages(input) {
        const previewContainer = document.getElementById('newImagesPreview');
        previewContainer.innerHTML = '';

        if (input.files && input.files.length > 0) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                const reader = new FileReader();

                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'relative border-2 border-green-500 rounded-xl overflow-hidden bg-gray-50 shadow-md';
                    previewDiv.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-32 object-cover">
                        <div class="absolute top-2 right-2 bg-green-500 text-white rounded-full px-2 py-1 text-xs font-bold">
                            جديد
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-70 text-white text-xs text-center py-1">
                            ${(file.size / 1024).toFixed(0)} KB
                        </div>
                        <button type="button" onclick="this.parentElement.remove()"
                                class="absolute top-2 left-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600">
                            ×
                        </button>
                    `;
                    previewContainer.appendChild(previewDiv);
                }

                reader.readAsDataURL(file);
            }
        }
    }

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

        // التحقق من حجم الصور الجديدة
        const newImages = document.querySelector('input[name="images[]"]');
        if (newImages && newImages.files) {
            for (let i = 0; i < newImages.files.length; i++) {
                const file = newImages.files[i];
                if (file.size > 2 * 1024 * 1024) {
                    e.preventDefault();
                    alert(` الصورة "${file.name}" حجمها يتجاوز 2 ميجابايت`);
                    return false;
                }
            }
        }

        // عرض ملخص للتغييرات
        const selectedImages = document.querySelectorAll('.delete-checkbox:checked');
        const newImagesCount = newImages ? newImages.files.length : 0;

        if (selectedImages.length > 0 || newImagesCount > 0) {
            let message = 'سيتم ';
            if (selectedImages.length > 0) {
                message += `حذف ${selectedImages.length} صورة `;
            }
            if (selectedImages.length > 0 && newImagesCount > 0) {
                message += 'و ';
            }
            if (newImagesCount > 0) {
                message += `إضافة ${newImagesCount} صورة جديدة`;
            }
            message += '. هل أنت متأكد؟';

            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        }
    });

    // تأكيد الإلغاء
    const cancelBtn = document.querySelector('a[href="{{ route('ads.index') }}"]');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من إلغاء التعديل؟ سيتم فقدان التغييرات غير المحفوظة.')) {
                e.preventDefault();
            }
        });
    }
</script>

@endsection
