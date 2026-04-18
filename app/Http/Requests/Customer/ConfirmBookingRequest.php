<?php
// app/Http/Requests/Customer/ConfirmBookingRequest.php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('customer');
    }

    public function rules(): array
    {
        return [
            'salon_id' => ['required', 'exists:salons,id'],
            'barber_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:barber_services,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'salon_id.required' => 'يجب اختيار الصالون',
            'barber_id.required' => 'يجب اختيار الحلاق',
            'service_id.required' => 'يجب اختيار الخدمة',
            'appointment_date.required' => 'يجب اختيار تاريخ الموعد',
            'appointment_time.required' => 'يجب اختيار وقت الموعد',
        ];
    }
}
