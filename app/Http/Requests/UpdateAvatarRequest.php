<?php
// app/Http/Requests/UpdateAvatarRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048'
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'الصورة مطلوبة',
            'avatar.image' => 'الملف يجب أن يكون صورة',
            'avatar.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, jpg, png, webp',
            'avatar.max' => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت',
        ];
    }
}
