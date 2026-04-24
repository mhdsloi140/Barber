<?php
// app/Http/Requests/RatingRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('customer');
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'exists:appointments,id'],

            // تقييم الحلاق (اختياري)
            'barber_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'barber_comment' => ['nullable', 'string', 'max:500'],

            // تقييم الصالون (اختياري)
            'salon_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'salon_comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => 'معرف الحجز مطلوب',
            'appointment_id.exists' => 'الحجز غير موجود',
            'barber_rating.min' => 'تقييم الحلاق يجب أن يكون 1 على الأقل',
            'barber_rating.max' => 'تقييم الحلاق يجب أن يكون 5 كحد أقصى',
            'salon_rating.min' => 'تقييم الصالون يجب أن يكون 1 على الأقل',
            'salon_rating.max' => 'تقييم الصالون يجب أن يكون 5 كحد أقصى',
            'barber_comment.max' => 'تعليق الحلاق لا يجب أن يتجاوز 500 حرف',
            'salon_comment.max' => 'تعليق الصالون لا يجب أن يتجاوز 500 حرف',
        ];
    }

    /**
     * التحقق من أن الحجز يخص هذا الزبون
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $appointment = \App\Models\Appointment::find($this->appointment_id);

            if ($appointment && $appointment->customer_id !== auth()->id()) {
                $validator->errors()->add('appointment_id', 'هذا الحجز لا يخصك');
            }

            // التحقق من أن الحجز مكتمل
            if ($appointment && $appointment->status !== 'completed') {
                $validator->errors()->add('appointment_id', 'لا يمكن تقييم إلا الحجوزات المكتملة');
            }

            // التحقق من أنه تم إدخال تقييم واحد على الأقل
            if (!$this->barber_rating && !$this->salon_rating) {
                $validator->errors()->add('rating', 'يجب إدخال تقييم للحلاق أو للصالون على الأقل');
            }
        });
    }
}
