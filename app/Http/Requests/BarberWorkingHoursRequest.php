<?php
// app/Http/Requests/BarberWorkingHoursRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarberWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('barber');
    }

    public function rules(): array
    {
        return [
            'working_hours' => ['required', 'array'],
            'working_hours.*.day' => ['required', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'working_hours.*.is_open' => ['required', 'boolean'],
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
            'working_hours.required' => 'أوقات العمل مطلوبة',
            'working_hours.*.day.required' => 'اليوم مطلوب',
            'working_hours.*.day.in' => 'اليوم غير صالح',
            'working_hours.*.is_open.required' => 'حالة اليوم مطلوبة',
            'working_hours.*.shift1_end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
