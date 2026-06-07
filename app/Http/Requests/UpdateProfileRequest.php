<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [

            'name' => 'sometimes|string|max:255',
            'phone' => [
                'sometimes',
                'string',
                'min:10',
                'max:15',
                Rule::unique('users')->ignore($user->id),
            ],
            // 'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,

            // كلمة المرور
            'current_password' => 'sometimes|required_with:password|string',
            'password' => 'sometimes|string|min:8|confirmed',

            // الصورة الشخصية (يمكن أن تكون ملف)
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB

            // حذف الصورة
            'delete_avatar' => 'sometimes|boolean',

            // إعدادات الإشعارات
            'notifications_enabled' => 'sometimes|boolean',

            // بيانات الصالون (لصاحب الصالون)
            'salon_name' => 'sometimes|string|max:255',
            'salon_address' => 'sometimes|string|max:500',
            'salon_phone' => 'sometimes|string|max:20',
            'salon_description' => 'nullable|string',

            // بيانات الحلاق
            'experience_years' => 'nullable|integer|min:0|max:50',
            'specialization' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'الاسم يجب أن يكون نصاً',
            'name.max' => 'الاسم لا يتجاوز 255 حرفاً',
            'phone.unique' => 'رقم الهاتف مستخدم من قبل',
            'phone.min' => 'رقم الهاتف يجب أن يكون 10 أرقام على الأقل',
            'phone.max' => 'رقم الهاتف لا يتجاوز 15 رقماً',
            'current_password.required_with' => 'كلمة المرور الحالية مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'avatar.image' => 'الملف يجب أن يكون صورة',
            'avatar.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, gif',
            'avatar.max' => 'حجم الصورة لا يتجاوز 5 ميجابايت',
            'notifications_enabled.boolean' => 'حقل الإشعارات يجب أن يكون true أو false',
            'delete_avatar.boolean' => 'حقل حذف الصورة يجب أن يكون true أو false',
        ];
    }
}
