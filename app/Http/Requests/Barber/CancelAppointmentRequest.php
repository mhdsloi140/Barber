<?php

namespace App\Http\Requests\Barber;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class CancelAppointmentRequest extends FormRequest
{
         public function authorize(): bool
    {
        return auth()->user()?->hasRole('barber');
    }

    public function rules(): array
    {
        return [

        ];
    }

    public function messages(): array
    {
        return [];
    }

    /**
     * التحقق من أن الحجز يخص هذا الحلاق
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $appointmentId = $this->route('id');

            if (!$appointmentId) {
                $validator->errors()->add('id', 'معرف الحجز مطلوب');
                return;
            }

            $appointment = Appointment  ::find($appointmentId);

            if (!$appointment) {
                $validator->errors()->add('id', 'الحجز غير موجود');
                return;
            }

            if ($appointment->barber_id !== auth()->id()) {
                $validator->errors()->add('id', 'هذا الحجز لا يخصك');
            }
        });
    }
}
