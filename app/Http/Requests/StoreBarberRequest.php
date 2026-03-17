<?php
// app/Http/Requests/StoreBarberRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBarberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('salon_owner');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'unique:users,phone', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
            // 'specialization' => ['required', 'string', 'max:255'],
            // 'password' => ['required', 'string', 'min:8', 'confirmed'],


        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',
            'specialization.required' => 'التخصص مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
        ];
    }
}
