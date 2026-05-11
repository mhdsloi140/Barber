<?php
// app/Http/Requests/BarberWorkingHoursRequest.php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarberWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        //  dd($this->all());
        return auth()->user()?->hasRole('barber');
    }

    public function rules(): array
    {
        return [
            'working_hours' => ['required', 'array'],
            'working_hours.*.day' => ['required', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'working_hours.*.is_open' => ['required', 'boolean'],


            'working_hours.*.start' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i'],
            'working_hours.*.end' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i', 'after:working_hours.*.start'],
        ];
    }

    public function messages(): array
    {
        return [
            'working_hours.required' => 'أوقات العمل مطلوبة',
            'working_hours.*.day.required' => 'اليوم مطلوب',
            'working_hours.*.day.in' => 'اليوم غير صالح',
            'working_hours.*.is_open.required' => 'حالة اليوم مطلوبة',


            'working_hours.*.start.required_if' => 'وقت البدء مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.end.required_if' => 'وقت النهاية مطلوب عندما يكون اليوم مفتوحاً',
            'working_hours.*.end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
