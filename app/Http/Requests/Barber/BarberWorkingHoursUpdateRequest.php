<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarberWorkingHoursUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('barber');
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1'],
            'days.*.day' => ['required', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'days.*.is_open' => ['required', 'boolean'],
            'days.*.start' => ['required_if:days.*.is_open,true', 'nullable', 'date_format:H:i'],
            'days.*.end' => ['required_if:days.*.is_open,true', 'nullable', 'date_format:H:i', 'after:days.*.start'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.required' => 'يجب إدخال يوم واحد على الأقل',
            'days.array' => 'يجب أن تكون الأيام مصفوفة',
            'days.min' => 'يجب إدخال يوم واحد على الأقل',
            'days.*.day.required' => 'اليوم مطلوب',
            'days.*.day.in' => 'اليوم غير صالح',
            'days.*.is_open.required' => 'حالة اليوم مطلوبة',
            'days.*.start.required_if' => 'وقت البدء مطلوب عندما يكون اليوم مفتوحاً',
            'days.*.end.required_if' => 'وقت النهاية مطلوب عندما يكون اليوم مفتوحاً',
            'days.*.end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
