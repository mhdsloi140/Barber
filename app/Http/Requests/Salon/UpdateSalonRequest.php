<?php
// app/Http/Requests/Salon/UpdateSalonRequest.php

namespace App\Http\Requests\Salon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        // dd(auth()->user());
        // dd($this->all());
        return auth()->user()?->hasRole('salon_owner');
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            // بيانات المستخدم
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]+$/', 'min:10', Rule::unique('users')->ignore($userId)],

            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required_with:password'],

            // الصورة الشخصية
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // بيانات الصالون
            'salon_name' => ['nullable', 'string', 'max:255'],
            'salon_address' => ['nullable', 'string', 'max:255'],
            'salon_phone' => ['nullable', 'string', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:500'],

            // صور الصالون (للإضافة فقط)
            'new_images' => ['nullable', 'array'],
            'new_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // أضافات للحذف
            'delete_image_ids' => ['nullable', 'array'],
            'delete_image_ids.*' => ['exists:media,id'],


            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day' => ['required_with:working_hours', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.is_open' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.start' => ['nullable', 'date_format:H:i'],
            'working_hours.*.end' => ['nullable', 'date_format:H:i', 'after:working_hours.*.start'],
            'notifications_enabled' => 'sometimes|boolean',

        ];
    }

    public function messages(): array
    {
        return [
            // رسائل المستخدم
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',
            'phone.min' => 'رقم الهاتف يجب أن لا يقل عن 10 أرقام',
            'phone.max' => 'رقم الهاتف يجب أن لا يزيد عن 15 رقماً',

            // رسائل كلمة المرور
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'password_confirmation.required_with' => 'تأكيد كلمة المرور مطلوب',

            // رسائل الصورة الشخصية
            'avatar.image' => 'الملف يجب أن يكون صورة',
            'avatar.mimes' => 'الصورة الشخصية يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'avatar.max' => 'حجم الصورة الشخصية لا يجب أن يتجاوز 5 ميجابايت',

            // رسائل صور الصالون
            'new_images.array' => 'الصور يجب أن تكون مصفوفة',
            'new_images.*.image' => 'الملف يجب أن يكون صورة',
            'new_images.*.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'new_images.*.max' => 'حجم الصورة لا يجب أن يتجاوز 5 ميجابايت',

            // رسائل حذف الصور
            'delete_image_ids.array' => 'معرفات الصور المحذوفة يجب أن تكون مصفوفة',
            'delete_image_ids.*.exists' => 'الصورة غير موجودة',

            //  رسائل أوقات العمل
            'working_hours.array' => 'أوقات العمل يجب أن تكون مصفوفة',
            'working_hours.*.day.required_with' => 'اليوم مطلوب عند إضافة أوقات العمل',
            'working_hours.*.day.in' => 'اليوم غير صالح',
            'working_hours.*.is_open.required_with' => 'حالة اليوم مطلوبة',
            'working_hours.*.start.date_format' => 'صيغة وقت البدء غير صحيحة (مطلوب H:i)',
            'working_hours.*.end.date_format' => 'صيغة وقت النهاية غير صحيحة (مطلوب H:i)',
            'working_hours.*.end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',

            'notifications_enabled.boolean' => 'حقل الإشعارات يجب أن يكون true أو false',

        ];
    }
}
