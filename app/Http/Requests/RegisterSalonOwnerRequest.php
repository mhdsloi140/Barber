<?php
// app/Http/Requests/RegisterSalonOwnerRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSalonOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // بيانات المستخدم
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'unique:users,phone',
                'regex:/^(077|078|079)[0-9]{8}$/',
                'min:11',
                'max:11',
            ],
            'password' => ['required', 'string', 'min:6'],

            // بيانات الصالون
            'salon_name' => ['required', 'string', 'max:255'],
            'salon_address' => ['required', 'string', 'max:255'],
            'salon_phone' => [
                'nullable',
                'string',
                'regex:/^(077|078|079)[0-9]{8}$/',
                'min:11',
                'max:11',
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // الصورة الشخصية
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // هل يعمل كحلاق في الصالون؟
            'works_as_barber' => ['nullable', 'boolean'],

            // الصور الجديدة
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // أوقات العمل
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day' => ['required_with:working_hours', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.is_open' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.shift1_start' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i'],
            'working_hours.*.shift1_end' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i', 'after:working_hours.*.shift1_start'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // تنسيق رقم هاتف المستخدم
        if ($this->has('phone')) {
            $this->merge([
                'phone' => $this->normalizePhoneNumber($this->phone)
            ]);
        }

        // تنسيق رقم هاتف الصالون
        if ($this->has('salon_phone') && $this->salon_phone) {
            $this->merge([
                'salon_phone' => $this->normalizePhoneNumber($this->salon_phone)
            ]);
        }

        // تحويل working_hours
        if ($this->has('working_hours') && is_array($this->working_hours)) {
            $converted = [];
            foreach ($this->working_hours as $index => $hours) {
                if (is_array($hours)) {
                    $converted[$index] = [
                        'day' => $hours['day'] ?? null,
                        'is_open' => filter_var($hours['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'shift1_start' => $hours['shift1_start'] ?? null,
                        'shift1_end' => $hours['shift1_end'] ?? null,
                    ];
                }
            }
            $this->merge(['working_hours' => $converted]);
        }

        // تحويل works_as_barber إلى boolean
        if ($this->has('works_as_barber')) {
            $this->merge([
                'works_as_barber' => filter_var($this->works_as_barber, FILTER_VALIDATE_BOOLEAN)
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

    public function messages(): array
    {
        return [
            // رسائل المستخدم
            'name.required' => 'اسم المستخدم مطلوب',
            'name.string' => 'اسم المستخدم يجب أن يكون نصاً',
            'name.max' => 'اسم المستخدم يجب ألا يزيد عن 255 حرفاً',

            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يكون رقم عراقي صحيح (يبدأ بـ 077 أو 078 أو 079 ويتكون من 11 رقم)',
            'phone.min' => 'رقم الهاتف يجب أن يكون 11 رقم',
            'phone.max' => 'رقم الهاتف يجب أن يكون 11 رقم',

            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',

            // رسائل الصالون
            'salon_name.required' => 'اسم الصالون مطلوب',
            'salon_name.string' => 'اسم الصالون يجب أن يكون نصاً',
            'salon_name.max' => 'اسم الصالون يجب ألا يزيد عن 255 حرفاً',

            'salon_address.required' => 'عنوان الصالون مطلوب',
            'salon_address.string' => 'عنوان الصالون يجب أن يكون نصاً',
            'salon_address.max' => 'عنوان الصالون يجب ألا يزيد عن 255 حرفاً',

            'salon_phone.regex' => 'رقم هاتف الصالون يجب أن يكون رقم عراقي صحيح (يبدأ بـ 077 أو 078 أو 079 ويتكون من 11 رقم)',
            'salon_phone.min' => 'رقم هاتف الصالون يجب أن يكون 11 رقم',
            'salon_phone.max' => 'رقم هاتف الصالون يجب أن يكون 11 رقم',

            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180',

            // رسائل الصور
            'avatar.image' => 'الصورة الشخصية يجب أن تكون صورة',
            'avatar.mimes' => 'الصورة الشخصية يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'avatar.max' => 'حجم الصورة الشخصية يجب ألا يتجاوز 5 ميجابايت',

            'images.array' => 'الصور يجب أن تكون مصفوفة',
            'images.*.image' => 'كل ملف يجب أن يكون صورة',
            'images.*.mimes' => 'الصور يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'images.*.max' => 'حجم كل صورة يجب ألا يتجاوز 5 ميجابايت',

            'works_as_barber.boolean' => 'حقل يعمل كحلاق يجب أن يكون صحيح أو خطأ',

            // رسائل أوقات العمل
            'working_hours.array' => 'أوقات العمل يجب أن تكون مصفوفة',
            'working_hours.*.day.required_with' => 'اليوم مطلوب',
            'working_hours.*.day.in' => 'اليوم يجب أن يكون واحداً من: الأحد, الإثنين, الثلاثاء, الأربعاء, الخميس, الجمعة, السبت',
            'working_hours.*.is_open.required_with' => 'حالة اليوم مطلوبة',
            'working_hours.*.is_open.boolean' => 'حالة اليوم يجب أن تكون مفتوح أو مغلق',
            'working_hours.*.shift1_start.required_if' => 'وقت البدء مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.shift1_end.required_if' => 'وقت النهاية مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.shift1_start.date_format' => 'صيغة وقت البدء غير صحيحة (مطلوب H:i)',
            'working_hours.*.shift1_end.date_format' => 'صيغة وقت النهاية غير صحيحة (مطلوب H:i)',
            'working_hours.*.shift1_end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }

    /**
     * الحصول على بيانات صاحب الصالون بعد التنسيق
     */
    public function getSalonOwnerData(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'password' => $this->password,
            'salon_name' => $this->salon_name,
            'salon_address' => $this->salon_address,
            'salon_phone' => $this->salon_phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'works_as_barber' => $this->works_as_barber ?? false,
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
