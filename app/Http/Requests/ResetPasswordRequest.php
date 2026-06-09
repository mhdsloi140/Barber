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
            // رسائل حقل phone
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.min' => 'رقم الهاتف يجب أن يكون 10 أرقام على الأقل',
            'phone.max' => 'رقم الهاتف يجب ألا يزيد عن 15 رقم',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصاً',
            'phone.exists' => 'لا يوجد حساب مرتبط بهذا الرقم',

            // رسائل حقل code
            'code.required' => 'رمز التحقق مطلوب',
            'code.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
            'code.regex' => 'رمز التحقق يجب أن يحتوي على أرقام فقط',
            'code.string' => 'رمز التحقق يجب أن يكون نصاً',

            // رسائل حقل password
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً',
        ];
    }

    /**
     * الحصول على قواعد التحقق المخصصة بعد التحقق الأساسي
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // يمكن إضافة تحقق مخصص هنا إذا لزم الأمر
        });
    }

    /**
     * إعداد البيانات قبل التحقق
     */
    protected function prepareForValidation()
    {
        // تنسيق رقم الهاتف إذا لزم الأمر
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhoneNumber($this->phone)
            ]);
        }
    }

    /**
     * تنسيق رقم الهاتف
     */
    protected function normalizePhoneNumber($phone)
    {
     
        $phone = preg_replace('/[^0-9]/', '', $phone);


        if (str_starts_with($phone, '0')) {
            $phone = '966' . substr($phone, 1);
        }

        return $phone;
    }
}
