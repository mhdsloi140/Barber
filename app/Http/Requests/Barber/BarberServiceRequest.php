<?php


namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;

class BarberServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('barber');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // 'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            // 'description_ar' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الخدمة مطلوب',
            'price.required' => 'سعر الخدمة مطلوب',
            'price.numeric' => 'السعر يجب أن يكون رقماً',
            'price.min' => 'السعر يجب أن يكون 0 أو أكثر',
            'duration_minutes.required' => 'مدة الخدمة مطلوبة',
            'duration_minutes.min' => 'مدة الخدمة يجب أن تكون 5 دقائق على الأقل',
            'duration_minutes.max' => 'مدة الخدمة لا يجب أن تتجاوز 240 دقيقة',
        ];
    }
}
