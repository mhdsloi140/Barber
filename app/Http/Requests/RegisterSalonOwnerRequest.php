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

            // صورة الصالون
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

           
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day' => ['required_with:working_hours', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.is_open' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.shift1_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.shift1_end' => ['nullable', 'date_format:H:i', 'after:working_hours.*.shift1_start'],
            'working_hours.*.shift2_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.shift2_end' => ['nullable', 'date_format:H:i', 'after:working_hours.*.shift2_start'],
            'working_hours.*.break_start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.break_end' => ['nullable', 'date_format:H:i', 'after:working_hours.*.break_start'],
        ];
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
            'working_hours.*.day.in' => 'اليوم غير صالح',
            'working_hours.*.is_open.required_with' => 'حالة اليوم مطلوبة',
            'working_hours.*.shift1_end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
