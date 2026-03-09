<?php
// app/Http/Requests/UpdateProfileRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth()->user();

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'min:10',
                'max:15',
                Rule::unique('users')->ignore($user->id)
            ],

            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required_with:password'],
        ];

        // حقول إضافية لصاحب الصالون
        if ($user->hasRole('salon_owner')) {
            $rules['center_name'] = ['sometimes', 'string', 'max:255'];
            $rules['address'] = ['sometimes', 'string', 'max:255'];
            $rules['salon_phone'] = ['sometimes', 'string', 'max:15'];
            $rules['description'] = ['sometimes', 'string', 'max:500'];
        }

        // حقول إضافية للحلاق
        if ($user->hasRole('barber')) {
            $rules['experience_years'] = ['sometimes', 'integer', 'min:0', 'max:50'];
            $rules['specialization'] = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.string' => 'الاسم يجب أن يكون نصاً',
            'name.max' => 'الاسم لا يجب أن يتجاوز 255 حرف',
            'phone.min' => 'رقم الهاتف يجب أن يكون 10 أرقام على الأقل',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'email.email' => 'البريد الإلكتروني غير صالح',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'password_confirmation.required_with' => 'حقل تأكيد كلمة المرور مطلوب',
        ];
    }
}
