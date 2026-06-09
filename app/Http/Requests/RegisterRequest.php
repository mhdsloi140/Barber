<?php
// app/Http/Requests/RegisterRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'unique:users,phone',
                'regex:/^(077|078|079)[0-9]{8}$/',  
                'min:11',
                'max:11',
            ],
            'password' => ['required', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            // حقول صاحب الصالون (اختيارية)
            'salon_name' => ['nullable', 'string', 'max:255', 'required_if:user_type,salon_owner'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'salon_location' => ['nullable', 'string', 'max:500', 'required_if:user_type,salon_owner'],
            'user_type' => ['nullable', 'in:customer,salon_owner'],
        ];
    }

    public function messages(): array
    {
        return [
            // رسائل الاسم
            'name.required' => 'الاسم مطلوب',
            'name.string' => 'الاسم يجب أن يكون نصاً',
            'name.max' => 'الاسم يجب ألا يزيد عن 255 حرفاً',

            // رسائل رقم الهاتف
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم عراقي صحيح (يبدأ بـ 077 أو 078 أو 079 ويتكون من 11 رقم)',
            'phone.min' => 'رقم الهاتف يجب أن يكون 11 رقم',
            'phone.max' => 'رقم الهاتف يجب أن يكون 11 رقم',

            // رسائل كلمة المرور
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',

            // رسائل الصورة
            'avatar.image' => 'الملف يجب أن يكون صورة',
            'avatar.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'avatar.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت',

            // رسائل صاحب الصالون
            'salon_name.required_if' => 'اسم الصالون مطلوب لتسجيل صاحب صالون',
            'salon_location.required_if' => 'موقع الصالون مطلوب لتسجيل صاحب صالون',
            'salon_name.string' => 'اسم الصالون يجب أن يكون نصاً',
            'salon_location.string' => 'موقع الصالون يجب أن يكون نصاً',

            'owner_name.string' => 'اسم صاحب الصالون يجب أن يكون نصاً',
            'user_type.in' => 'نوع المستخدم يجب أن يكون customer أو salon_owner',
        ];
    }

    /**
     * تحديد نوع المستخدم
     */
    public function getUserType(): string
    {
        if ($this->has('user_type')) {
            return $this->user_type;
        }

        // إذا لم يتم تحديد user_type، تحقق من وجود حقول الصالون
        return ($this->has('salon_name') || $this->has('owner_name') || $this->has('salon_location'))
            ? 'salon_owner'
            : 'customer';
    }

    /**
     * الحصول على بيانات العميل
     */
    public function getCustomerData(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'password' => $this->password,
        ];
    }

    /**
     * الحصول على بيانات صاحب الصالون
     */
    public function getSalonOwnerData(): array
    {
        return [
            'name' => $this->owner_name ?? $this->name,
            'phone' => $this->phone,
            'password' => $this->password,
            'salon_name' => $this->salon_name,
            'salon_location' => $this->salon_location,
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

        // التأكد من أن الرقم يبدأ بـ 0 ويتكون من 11 رقم
        if (strlen($phone) === 10 && !str_starts_with($phone, '0')) {
            $phone = '0' . $phone;
        }

        return $phone;
    }

    /**
     * التحقق من صحة رقم عراقي
     */
    public static function isValidIraqiPhone($phone): bool
    {
        return (bool) preg_match('/^(077|078|079)[0-9]{8}$/', $phone);
    }
}
