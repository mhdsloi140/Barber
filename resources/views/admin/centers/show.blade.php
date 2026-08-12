@extends('layout.app')

@section('content')

<div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100">


    <div class="p-4 md:p-6 lg:p-8">

        <!-- معلومات الصالون والإحصائيات -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- معلومات الصالون -->
            <div
                class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-all duration-300">
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-5 py-3 border-b border-purple-100">
                    <h3 class="font-bold text-lg text-purple-700 flex items-center gap-2">
                        <i class="fas fa-info-circle text-purple-500"></i>
                        معلومات الصالون
                    </h3>
                </div>
                <div class="p-5 space-y-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-tag text-purple-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-400 mb-0.5">اسم الصالون</p>
                            <p class="text-gray-800 font-semibold">{{ $salon->name ?? '---' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-user text-purple-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-400 mb-0.5">صاحب الصالون</p>
                            <p class="text-gray-800 font-semibold">
                                @if($salon->owner)
                                <i class="fas fa-user-circle text-purple-500 ml-1"></i>
                                {{ $salon->owner->name ?? 'غير معروف' }}
                                @else
                                <span class="text-orange-500"><i class="fas fa-exclamation-triangle ml-1"></i>غير
                                    مرتبط</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-phone-alt text-purple-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-400 mb-0.5">رقم الهاتف</p>
                            <p class="text-gray-800 font-semibold dir-ltr">{{ $salon->phone ?? '---' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-location-dot text-purple-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-400 mb-0.5">العنوان</p>
                            <p class="text-gray-800">{{ $salon->address ?? 'غير محدد' }}</p>
                        </div>
                    </div>

                    @if(isset($salon->created_at))
                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-calendar-alt text-purple-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-xs text-gray-400 mb-0.5">تاريخ الإنشاء</p>
                            <p class="text-gray-800">{{ $salon->created_at->format('Y-m-d') }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- إحصائيات سريعة -->
            <!-- إحصائيات سريعة -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-5 py-3 border-b border-purple-100">
                    <h3 class="font-bold text-lg text-purple-700">
                        <i class="fas fa-chart-line text-purple-500 ml-2"></i>
                        إحصائيات سريعة
                    </h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

                        <!-- إجمالي الحجوزات -->
                        <div class="text-center p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition duration-200">
                            <div
                                class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">{{ $totalAppointments ?? 0 }}</p>
                            <p class="text-xs text-gray-500">إجمالي الحجوزات</p>
                        </div>

                        <!-- متوسط التقييم -->
                        <div
                            class="text-center p-4 rounded-xl bg-yellow-50 hover:bg-yellow-100 transition duration-200">
                            <div
                                class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-star text-yellow-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">
                                {{ number_format($averageRating ?? 0, 1) }}
                                <span class="text-sm text-gray-400">/ 5</span>
                            </p>
                            <p class="text-xs text-gray-500">متوسط التقييم</p>
                            @if($ratingsCount > 0)
                            <p class="text-xs text-gray-400 mt-1">({{ $ratingsCount }} تقييم)</p>
                            @endif
                        </div>

                        <!-- عدد الحلاقين -->
                        <div
                            class="text-center p-4 rounded-xl bg-purple-50 hover:bg-purple-100 transition duration-200">
                            <div
                                class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-cut text-purple-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">{{ $barbersCount ?? 0 }}</p>
                            <p class="text-xs text-gray-500">عدد الحلاقين</p>
                        </div>

                        <!-- عدد الصور -->
                        <div class="text-center p-4 rounded-xl bg-green-50 hover:bg-green-100 transition duration-200">
                            <div
                                class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-image text-green-600 text-xl"></i>
                            </div>
                            <p class="text-2xl font-bold text-gray-800">{{ $imagesCount ?? 0 }}</p>
                            <p class="text-xs text-gray-500">عدد الصور</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- صور الصالون - عرض أفقي بجانب بعضها -->
        @php
        $images = $salon->getMedia('salon_images');
        @endphp
        @if($images && $images->count() > 0)
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 bg-gradient-to-b from-purple-500 to-pink-500 rounded-full"></div>
                <i class="fas fa-images text-purple-500 text-sm"></i>
                <h3 class="font-bold text-purple-700">صور الصالون</h3>
                <span class="text-xs text-gray-400">({{ $images->count() }} صورة)</span>
            </div>

            <div class="overflow-x-auto overflow-y-hidden pb-2" style="scrollbar-width: thin;">
                <div class="flex gap-2" style="min-width: min-content;">
                    @foreach($images as $image)
                    <div class="group relative rounded-lg overflow-hidden shadow-sm bg-gray-100 flex-shrink-0 cursor-pointer"
                        style="width: 200px; height:200px;">
                        <img src="{{ $image->getUrl('thumb') }}" alt="{{ $salon->name }}"
                            class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                            loading="lazy">
                        <div
                            class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-all duration-200 flex items-center justify-center">
                            <a href="{{ $image->getUrl() }}" target="_blank"
                                class="bg-white rounded-full p-1 shadow-md hover:scale-110 transition">
                                <i class="fas fa-search text-purple-600 text-xs"></i>
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- قائمة الحلاقين -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <div class="w-1 h-5 bg-gradient-to-b from-purple-500 to-pink-500 rounded-full"></div>
                <i class="fas fa-users text-purple-500 text-sm"></i>
                <h3 class="font-bold text-purple-700">قائمة الحلاقين</h3>
                <span class="text-xs text-gray-400">({{ $salon->barbers()->count() ?? 0 }})</span>
            </div>

            @if($salon->barbers && $salon->barbers->count() > 0)
            <div class="overflow-x-auto rounded-2xl border border-gray-200 shadow-sm">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                            <th class="py-3 px-3 text-right text-sm font-bold text-gray-600">#</th>
                            <th class="py-3 px-3 text-right text-sm font-bold text-gray-600">الحلاق</th>
                            <th class="py-3 px-3 text-right text-sm font-bold text-gray-600">رقم الهاتف</th>
                            <th class="py-3 px-3 text-center text-sm font-bold text-gray-600">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salon->barbers as $index => $barber)
                        <tr class="border-b border-gray-100 hover:bg-purple-50/30 transition duration-200">
                            <td class="py-3 px-3 text-gray-500 font-medium">{{ $index + 1 }}</td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-7 h-7 bg-gradient-to-br from-purple-400 to-pink-400 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                        {{ substr($barber->name ?? '?', 0, 1) }}
                                    </div>
                                    <span class="font-semibold text-gray-800 text-sm">{{ $barber->name ?? '---'
                                        }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 dir-ltr text-gray-600 text-sm">{{ $barber->phone ?? '---' }}</td>
                            <td class="py-3 px-3 text-center">
                                @if(isset($barber->is_active) && $barber->is_active)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                                    <i class="fas fa-circle-check text-xs"></i>
                                    نشط
                                </span>
                                @else
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                                    <i class="fas fa-circle-exclamation text-xs"></i>
                                    غير نشط
                                </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-10 bg-gray-50 rounded-2xl border border-gray-100">
                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-cut fa-xl text-gray-300"></i>
                </div>
                <p class="text-gray-400">لا يوجد حلاقين في هذا الصالون حالياً</p>
            </div>
            @endif
        </div>
    </div>
</div>

<style>
    .dir-ltr {
        direction: ltr;
        display: inline-block;
    }

    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .overflow-x-auto::-webkit-scrollbar {
        height: 4px;
    }

    .overflow-x-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: #c084fc;
        border-radius: 10px;

    }
</style>

@endsection
