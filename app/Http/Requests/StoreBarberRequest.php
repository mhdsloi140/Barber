<?php


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
            'phone' => [
                'required',
                'string',
                'unique:users,phone',
                'regex:/^(077|078|079)[0-9]{8}$/',
                'min:11',
                'max:11',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // رسائل الاسم
            'name.required' => 'اسم الحلاق مطلوب',
            'name.string' => 'اسم الحلاق يجب أن يكون نصاً',
            'name.max' => 'اسم الحلاق يجب ألا يزيد عن 255 حرفاً',

            // رسائل رقم الهاتف
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم عراقي صحيح (يبدأ بـ 077 أو 078 أو 079 ويتكون من 11 رقم)',
            'phone.min' => 'رقم الهاتف يجب أن يكون 11 رقم',
            'phone.max' => 'رقم الهاتف يجب أن يكون 11 رقم',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصاً',
        ];
    }

    /**
     * تنسيق رقم الهاتف قبل التحقق
     */
    protected function prepareForValidation()
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhoneNumber($this->phone)
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
     * الحصول على بيانات الحلاق بعد التنسيق
     */
    public function getBarberData(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
        ];
    }

    /**
     * التحقق من صحة رقم عراقي (دالة مساعدة)
     */
    public static function isValidIraqiPhone($phone): bool
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return (bool) preg_match('/^(077|078|079)[0-9]{8}$/', $phone);
    }
}
