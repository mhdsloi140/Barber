{{-- resources/views/admin/centers/_table_rows.blade.php --}}
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
    <td class="py-3 px-2">{{ $salon->created_at->format('Y-m-d') }}</td>
    <td class="py-3 px-2">
        <button onclick="openModal({{ $salon->id }})" class="btn-action btn-view" title="عرض التفاصيل">
            <i class="fas fa-eye"></i> عرض
        </button>
    </td>
</tr>
@empty
<tr>
    <td colspan="10" class="py-8 text-center text-gray-400">
        <i class="fas fa-store fa-3x mb-3 block text-gray-300"></i>
        <p>لا توجد نتائج مطابقة للبحث</p>
    </td>
</tr>
@endforelse
