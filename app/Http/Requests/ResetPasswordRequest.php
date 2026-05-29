<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string|min:10|max:15|exists:users,phone',
            'code' => 'required|string|size:6|regex:/^[0-9]+$/',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.exists' => 'لا يوجد حساب مرتبط بهذا الرقم',
            'code.required' => 'رمز التحقق مطلوب',
            'code.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
            'code.regex' => 'رمز التحقق يجب أن يحتوي على أرقام فقط',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ];
    }
}
