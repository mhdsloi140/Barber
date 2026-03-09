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
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'unique:users,phone', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
            'specialization' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // أيام العمل (مصفوفة)
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],

            // ساعات العمل (مصفوفة من الأوقات)
            'working_hours' => ['required', 'array'],
            'working_hours.*.day' => ['required', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'working_hours.*.start' => ['required', 'date_format:H:i'],
            'working_hours.*.end' => ['required', 'date_format:H:i', 'after:working_hours.*.start'],

            // فترة الراحة (اختياري)
            // 'break_hours' => ['nullable', 'array'],
            // 'break_hours.*.start' => ['required_with:break_hours', 'date_format:H:i'],
            // 'break_hours.*.end' => ['required_with:break_hours', 'date_format:H:i', 'after:break_hours.*.start'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',
            'specialization.required' => 'التخصص مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'working_days.required' => 'أيام العمل مطلوبة',
            'working_days.min' => 'يجب اختيار يوم عمل واحد على الأقل',
            'working_hours.required' => 'ساعات العمل مطلوبة',
            'working_hours.*.start.required' => 'وقت البدء مطلوب',
            'working_hours.*.end.required' => 'وقت النهاية مطلوب',
            'working_hours.*.end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
