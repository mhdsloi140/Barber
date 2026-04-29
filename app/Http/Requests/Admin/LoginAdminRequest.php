<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LoginAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:15', ],
            'password' => ['required', 'string', 'min:8', 'max:20'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'رقم الهاتف',
            'password' => 'كلمة المرور',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // رسائل حقل رقم الهاتف
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصاً صالحاً.',
            'phone.min' => 'رقم الهاتف يجب أن لا يقل عن 10 أرقام.',
            'phone.max' => 'رقم الهاتف يجب أن لا يزيد عن 15 رقمًا.',
            'phone.regex' => 'صيغة رقم الهاتف غير صحيحة. يجب أن يبدأ بـ 05 أو 9665 أو +9665 متبوعاً بـ 8 أرقام.',

            // رسائل حقل كلمة المرور
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.string' => 'كلمة المرور يجب أن تكون نصاً.',
            'password.min' => 'كلمة المرور يجب أن لا تقل عن 8 أحرف.',
            'password.max' => 'كلمة المرور يجب أن لا تزيد عن 20 حرفًا.',
        ];
    }

    /**
     * Prepare the data for validation.
     * يمكنك استخدام هذه الدالة لتنسيق رقم الهاتف قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        // إزالة أي مسافات من رقم الهاتف
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\s+/', '', $this->phone),
            ]);
        }
    }
}
