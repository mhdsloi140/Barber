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
            'phone' => ['required', 'string', 'unique:users,phone', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
            'password' => ['required', 'string', 'min:6'],

            // بيانات الصالون
            'salon_name' => ['required', 'string', 'max:255'],
            'salon_address' => ['required', 'string', 'max:255'],
            'salon_phone' => ['nullable', 'string', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // الصورة الشخصية
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // هل يعمل كحلاق في الصالون؟
            'works_as_barber' => ['nullable', 'boolean'],

            // الصور الجديدة
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // أوقات العمل - باستخدام shift1_start و shift1_end
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day' => ['required_with:working_hours', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.is_open' => ['required_with:working_hours', 'boolean'],

            // 🔴 التعديل هنا: استخدم shift1_start و shift1_end بدلاً من start و end
            'working_hours.*.shift1_start' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i'],
            'working_hours.*.shift1_end' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i', 'after:working_hours.*.shift1_start'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // تحويل working_hours
        if ($this->has('working_hours') && is_array($this->working_hours)) {
            $converted = [];
            foreach ($this->working_hours as $index => $hours) {
                if (is_array($hours)) {
                    $converted[$index] = [
                        'day' => $hours['day'] ?? null,
                        'is_open' => filter_var($hours['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'shift1_start' => $hours['shift1_start'] ?? null,  // 🔴 استخدم shift1_start
                        'shift1_end' => $hours['shift1_end'] ?? null,      // 🔴 استخدم shift1_end
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

        // تنظيف رقم الهاتف
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\s+/', '', $this->phone)
            ]);
        }
    }

    public function messages(): array
    {
        return [
            // رسائل المستخدم
            'name.required' => 'اسم المستخدم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',

            // رسائل الصالون
            'salon_name.required' => 'اسم الصالون مطلوب',
            'salon_address.required' => 'عنوان الصالون مطلوب',

            // رسائل أوقات العمل
            'working_hours.*.day.required_with' => 'اليوم مطلوب',
            'working_hours.*.is_open.required_with' => 'حالة اليوم مطلوبة',
            'working_hours.*.shift1_start.required_if' => 'وقت البدء مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.shift1_end.required_if' => 'وقت النهاية مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.shift1_start.date_format' => 'صيغة وقت البدء غير صحيحة (مطلوب H:i)',
            'working_hours.*.shift1_end.date_format' => 'صيغة وقت النهاية غير صحيحة (مطلوب H:i)',
            'working_hours.*.shift1_end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
