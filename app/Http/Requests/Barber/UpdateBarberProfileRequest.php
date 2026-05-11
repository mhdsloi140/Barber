<?php


namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBarberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('barber');
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            // بيانات الحلاق الأساسية
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]+$/', 'min:10', Rule::unique('users')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required_with:password'],

            // الصورة الشخصية
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            // أوقات العمل
            'working_hours' => ['nullable', 'array'],
            'working_hours.*.day' => ['required_with:working_hours', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.is_open' => ['required_with:working_hours', 'boolean'],
            'working_hours.*.start' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i'],
            'working_hours.*.end' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i', 'after:working_hours.*.start'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',
            'phone.min' => 'رقم الهاتف يجب أن لا يقل عن 10 أرقام',
            'phone.max' => 'رقم الهاتف يجب أن لا يزيد عن 15 رقماً',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'avatar.image' => 'الملف يجب أن يكون صورة',
            'avatar.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg, webp',
            'avatar.max' => 'حجم الصورة لا يجب أن يتجاوز 5 ميجابايت',
            'working_hours.*.start.required_if' => 'وقت البدء مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.end.required_if' => 'وقت النهاية مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
