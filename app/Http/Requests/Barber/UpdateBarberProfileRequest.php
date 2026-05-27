<?php
// app/Http/Requests/Barber/UpdateBarberProfileRequest.php

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

            
            'specialization_ids' => ['nullable'],
        ];
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        // تحويل specialization_ids إلى مصفوفة إذا كان نص JSON
        if ($this->has('specialization_ids')) {
            $specializationIds = $this->input('specialization_ids');

            // إذا كان نص JSON مثل "[1,2]"
            if (is_string($specializationIds) && str_starts_with($specializationIds, '[')) {
                $decoded = json_decode($specializationIds, true);
                if (is_array($decoded)) {
                    $this->merge([
                        'specialization_ids' => $decoded
                    ]);
                }
            }
            // إذا كان نص مفصول بفواصل مثل "1,2"
            elseif (is_string($specializationIds) && str_contains($specializationIds, ',')) {
                $ids = explode(',', $specializationIds);
                $ids = array_map('intval', array_map('trim', $ids));
                $this->merge([
                    'specialization_ids' => $ids
                ]);
            }
            // إذا كان رقم واحد مثل "1"
            elseif (is_string($specializationIds) && is_numeric($specializationIds)) {
                $this->merge([
                    'specialization_ids' => [(int)$specializationIds]
                ]);
            }
        }
    }

    /**
     * التحقق من صحة البيانات بعد التحضير
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // التحقق من وجود الاختصاصات في قاعدة البيانات
            if ($this->has('specialization_ids') && is_array($this->specialization_ids)) {
                $invalidIds = [];
                foreach ($this->specialization_ids as $id) {
                    if (!\App\Models\Specialization::where('id', $id)->exists()) {
                        $invalidIds[] = $id;
                    }
                }
                if (!empty($invalidIds)) {
                    $validator->errors()->add('specialization_ids', 'الاختصاصات التالية غير موجودة: ' . implode(', ', $invalidIds));
                }
            }
        });
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
