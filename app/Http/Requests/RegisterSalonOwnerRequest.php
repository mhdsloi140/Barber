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
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],

            // بيانات الصالون
            'salon_name' => ['required', 'string', 'max:255'],
            'salon_address' => ['required', 'string', 'max:255'],
            'salon_phone' => ['nullable', 'string', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // 5MB لكل صورة

            // أوقات العمل
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day' => ['required_with:working_hours', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.is_open' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.shift1_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.shift1_end' => ['nullable', 'date_format:H:i'],
            'working_hours.*.shift2_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.shift2_end' => ['nullable', 'date_format:H:i'],
            'working_hours.*.break_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.break_end' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * تحويل القيم قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        // تحويل working_hours من form-data إلى صيغة صحيحة
        if ($this->has('working_hours')) {
            $workingHours = $this->input('working_hours');

            if (is_array($workingHours)) {
                $converted = [];
                foreach ($workingHours as $index => $hours) {
                    if (is_array($hours)) {
                        $converted[$index] = [
                            'day' => $hours['day'] ?? null,
                            'is_open' => filter_var($hours['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN),
                            'shift1_start' => $hours['shift1_start'] ?? null,
                            'shift1_end' => $hours['shift1_end'] ?? null,
                            'shift2_start' => $hours['shift2_start'] ?? null,
                            'shift2_end' => $hours['shift2_end'] ?? null,
                            'break_start' => $hours['break_start'] ?? null,
                            'break_end' => $hours['break_end'] ?? null,
                        ];
                    }
                }
                $this->merge(['working_hours' => $converted]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المستخدم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'salon_name.required' => 'اسم الصالون مطلوب',
            'salon_address.required' => 'عنوان الصالون مطلوب',

            // رسائل الصور
            'images.array' => 'يجب إرسال الصور كمصفوفة',
            'images.*.image' => 'الملف يجب أن يكون صورة',
            'images.*.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'images.*.max' => 'حجم الصورة لا يجب أن يتجاوز 5 ميجابايت',

            // رسائل أوقات العمل
            'working_hours.*.day.required_with' => 'اليوم مطلوب',
            'working_hours.*.is_open.required_with' => 'حالة اليوم مطلوبة',
        ];
    }
}
