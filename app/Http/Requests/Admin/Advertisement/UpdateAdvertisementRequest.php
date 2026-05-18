<?php
// app/Http/Requests/Admin/Advertisement/UpdateAdvertisementRequest.php

namespace App\Http\Requests\Admin\Advertisement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvertisementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'link_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:media,id'
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'لا يمكن رفع أكثر من 10 صور',
            'images.*.image' => 'الملف يجب أن يكون صورة',
            'images.*.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp, gif',
            'images.*.max' => 'حجم الصورة لا يتجاوز 2 ميجابايت',
            'delete_images.*.exists' => 'الصورة المطلوب حذفها غير موجودة'
        ];
    }
}
