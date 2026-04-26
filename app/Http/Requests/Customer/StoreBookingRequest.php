<?php
// app/Http/Requests/Customer/StoreBookingRequest.php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('customer');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // الصالون والحلاق
            'salon_id' => ['required', 'exists:salons,id'],
            'barber_id' => ['required', 'exists:users,id'],

            // الخدمات (مصفوفة)
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['exists:barber_services,id'],

            // التاريخ والوقت
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'day' => ['required', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'time' => ['required', 'date_format:H:i'],

            // ملاحظات
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // رسائل الصالون والحلاق
            'salon_id.required' => 'يجب اختيار الصالون',
            'salon_id.exists' => 'الصالون غير موجود',
            'barber_id.required' => 'يجب اختيار الحلاق',
            'barber_id.exists' => 'الحلاق غير موجود',

            // رسائل الخدمات
            'service_ids.required' => 'يجب اختيار خدمة واحدة على الأقل',
            'service_ids.array' => 'الخدمات يجب أن تكون مصفوفة',
            'service_ids.min' => 'يجب اختيار خدمة واحدة على الأقل',
            'service_ids.*.exists' => 'إحدى الخدمات المختارة غير موجودة',

            // رسائل التاريخ والوقت
            'appointment_date.required' => 'يجب اختيار تاريخ الموعد',
            'appointment_date.date' => 'صيغة التاريخ غير صحيحة',
            'appointment_date.after_or_equal' => 'لا يمكن حجز موعد في تاريخ سابق',
            'day.required' => 'يجب اختيار اليوم',
            'day.in' => 'اليوم غير صالح',
            'time.required' => 'يجب اختيار الوقت',
            'time.date_format' => 'صيغة الوقت غير صحيحة (مثال: 09:00 أو 14:30)',

            // رسائل الملاحظات
            'notes.max' => 'الملاحظات لا يجب أن تتجاوز 500 حرف',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // إذا لم يتم إرسال اليوم ولكن يوجد appointment_date
        if ($this->has('appointment_date') && !$this->has('day')) {
            $date = \Carbon\Carbon::parse($this->appointment_date);
            $this->merge([
                'day' => strtolower($date->format('l')),
            ]);
        }
    }
}
