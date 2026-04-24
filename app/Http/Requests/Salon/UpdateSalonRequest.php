<?php
// app/Http/Requests/Salon/UpdateSalonRequest.php

namespace App\Http\Requests\Salon;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('salon_owner');
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            // بيانات المستخدم
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'unique:users,phone,' . $userId, 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
            
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required_with:password'],

            // بيانات الصالون
            'salon_name' => ['nullable', 'string', 'max:255'],
            'salon_address' => ['nullable', 'string', 'max:255'],
            'salon_phone' => ['nullable', 'string', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // صور الصالون (للإضافة فقط)
            'new_images' => ['nullable', 'array'],
            'new_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // أضافات للحذف
            'delete_image_ids' => ['nullable', 'array'],
            'delete_image_ids.*' => ['exists:media,id'],

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

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'new_images.*.image' => 'الملف يجب أن يكون صورة',
            'new_images.*.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'new_images.*.max' => 'حجم الصورة لا يجب أن يتجاوز 5 ميجابايت',
            'working_hours.*.day.required_with' => 'اليوم مطلوب',
        ];
    }
}
