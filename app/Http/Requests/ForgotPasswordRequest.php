<?php

namespace App\Http\Requests;

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
            'phone' => 'required|string|min:10|max:15|exists:users,phone',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.exists' => 'لا يوجد حساب مرتبط بهذا الرقم',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'رقم الهاتف',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => trim($this->phone),
            ]);
        }
    }
}
