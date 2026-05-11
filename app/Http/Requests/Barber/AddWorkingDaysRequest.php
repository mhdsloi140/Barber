<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddWorkingDaysRequest extends FormRequest
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
            'days.*.start' => ['required', 'date_format:H:i'],
            'days.*.end' => ['required', 'date_format:H:i', 'after:days.*.start'],
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
            'days.*.start.required' => 'وقت البدء مطلوب',
            'days.*.start.date_format' => 'صيغة وقت البدء غير صحيحة (مطلوب H:i)',
            'days.*.end.required' => 'وقت النهاية مطلوب',
            'days.*.end.date_format' => 'صيغة وقت النهاية غير صحيحة (مطلوب H:i)',
            'days.*.end.after' => 'وقت النهاية يجب أن يكون بعد وقت البدء',
        ];
    }
}
