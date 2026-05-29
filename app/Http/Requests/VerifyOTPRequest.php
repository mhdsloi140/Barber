<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyOTPRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // يسمح للجميع باستخدام هذا الـ Request (حتى غير المسجلين)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'code' => [
                'required',
                'string',
                'size:6',
                'regex:/^[0-9]+$/',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // user_id validation messages
            'user_id.required' => 'معرف المستخدم مطلوب',
            'user_id.integer' => 'معرف المستخدم يجب أن يكون رقماً',
            'user_id.exists' => 'المستخدم غير موجود',

            // code validation messages
            'code.required' => 'رمز التحقق مطلوب',
            'code.string' => 'رمز التحقق يجب أن يكون نصاً',
            'code.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
            'code.regex' => 'رمز التحقق يجب أن يحتوي على أرقام فقط',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'معرف المستخدم',
            'code' => 'رمز التحقق',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // تنظيف الرمز من أي مسافات أو أحرف إضافية
        if ($this->has('code')) {
            $this->merge([
                'code' => trim($this->code),
            ]);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();

        $response = response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'errors' => $errors,
        ], 422);

        throw new HttpResponseException($response);
    }
}
