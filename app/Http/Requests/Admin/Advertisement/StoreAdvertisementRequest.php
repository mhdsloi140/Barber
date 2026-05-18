<?php
// app/Http/Requests/Admin/Advertisement/StoreAdvertisementRequest.php

namespace App\Http\Requests\Admin\Advertisement;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvertisementRequest extends FormRequest
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
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp,gif|max:2048'
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'يجب رفع صورة واحدة على الأقل',
            'images.min' => 'يجب رفع صورة واحدة على الأقل',
            'images.max' => 'لا يمكن رفع أكثر من 10 صور',
            'images.*.image' => 'الملف يجب أن يكون صورة',
            'images.*.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp, gif',
            'images.*.max' => 'حجم الصورة لا يتجاوز 2 ميجابايت',
            'link_url.url' => 'الرابط يجب أن يكون رابط صحيح'
        ];
    }
}
