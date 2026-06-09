<?php


namespace App\Http\Requests\Salon;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
            'phone' => [
                'required',
                'string',
                'regex:/^(077|078|079)[0-9]{8}$/',
                'min:11',
                'max:11',
            ],
            'code' => [
                'required',
                'string',
                'size:6',
                'regex:/^[0-9]+$/'
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            // ===================== رسائل رقم الهاتف =====================
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم عراقي صحيح (يبدأ بـ 077 أو 078 أو 079 ويتكون من 11 رقم)',
            'phone.min' => 'رقم الهاتف يجب أن يكون 11 رقم',
            'phone.max' => 'رقم الهاتف يجب أن يكون 11 رقم',

            // ===================== رسائل رمز التحقق =====================
            'code.required' => 'رمز التحقق مطلوب',
            'code.size' => 'رمز التحقق يجب أن يكون 6 أرقام',
            'code.regex' => 'رمز التحقق يجب أن يحتوي على أرقام فقط',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // تنسيق رقم الهاتف قبل التحقق
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhoneNumber($this->phone)
            ]);
        }

        // إزالة المسافات من الكود إذا وجدت
        if ($this->has('code')) {
            $this->merge([
                'code' => preg_replace('/\s+/', '', $this->code)
            ]);
        }
    }

    /**
     * تنسيق رقم الهاتف العراقي
     */
    protected function normalizePhoneNumber($phone): string
    {
        // إزالة المسافات والشرطات والأقواس
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // إذا كان الرقم يبدأ بـ 964 (رمز العراق)
        if (str_starts_with($phone, '964')) {
            $phone = '0' . substr($phone, 3);
        }

        // إذا كان الرقم يبدأ بـ 00964
        if (str_starts_with($phone, '00964')) {
            $phone = '0' . substr($phone, 5);
        }

        // إذا كان الرقم يبدأ بـ +964
        if (str_starts_with($phone, '964')) {
            $phone = '0' . substr($phone, 3);
        }

        // إذا كان الرقم يبدأ بـ 7 (بدون 0)
        if (str_starts_with($phone, '77') || str_starts_with($phone, '78') || str_starts_with($phone, '79')) {
            $phone = '0' . $phone;
        }

        // التأكد من أن الرقم يبدأ بـ 0 ويتكون من 11 رقم
        if (strlen($phone) === 10 && !str_starts_with($phone, '0')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    /**
     * Get the phone number after validation.
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Get the verification code after validation.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * التحقق من صحة رمز التحقق (دالة مساعدة)
     */
    public static function isValidCode($code): bool
    {
        return (bool) preg_match('/^[0-9]{6}$/', $code);
    }
}
