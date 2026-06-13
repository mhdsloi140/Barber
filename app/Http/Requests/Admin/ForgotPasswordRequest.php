<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'min:11',
                'max:11',
                function ($attribute, $value, $fail) {
                    // تنظيف الرقم من أي أحرف غير رقمية
                    $phone = preg_replace('/[^0-9]/', '', $value);

                    // التحقق من الطول
                    if (strlen($phone) !== 11) {
                        $fail('رقم الهاتف يجب أن يتكون من 11 رقم');
                        return;
                    }

                    // التحقق من البداية (077, 078, 079)
                    $prefix = substr($phone, 0, 3);
                    if (!in_array($prefix, ['077', '078', '079'])) {
                        $fail('رقم الهاتف يجب أن يبدأ بـ 077 أو 078 أو 079');
                        return;
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.min' => 'رقم الهاتف يجب أن يكون 11 رقم',
            'phone.max' => 'رقم الهاتف يجب أن يكون 11 رقم',
        ];
    }
}
