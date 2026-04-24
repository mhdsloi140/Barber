<?php
// app/Http/Requests/Customer/StoreBookingRequest.php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        return true;
    }

    public function rules(): array
    {
        return [
            'salon_id' => ['required', 'exists:salons,id'],
            'barber_id' => ['required', 'exists:users,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['exists:barber_services,id'],
            'day' => ['required', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'salon_id.required' => 'يجب اختيار الصالون',
            'barber_id.required' => 'يجب اختيار الحلاق',
            'service_ids.required' => 'يجب اختيار خدمة واحدة على الأقل',
            'service_ids.array' => 'الخدمات يجب أن تكون مصفوفة',
            'service_ids.min' => 'يجب اختيار خدمة واحدة على الأقل',
            'day.required' => 'يجب اختيار اليوم',
            'day.in' => 'اليوم غير صالح',
            'time.required' => 'يجب اختيار الوقت',
            'time.date_format' => 'صيغة الوقت غير صحيحة',
        ];
    }
}
