<?php
// app/Http/Requests/Customer/StoreBookingRequest.php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

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

            'salon_id' => ['required', 'exists:salons,id'],
            'barber_id' => ['required', 'exists:users,id'],

            // الخدمات (مصفوفة أو نص)
            'service_ids' => ['required'],
            'service_ids.*' => ['exists:barber_services,id'],


            'appointment_date' => ['nullable', 'date', 'after_or_equal:today'],
            'day' => ['nullable', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {

        $this->prepareServiceIds();
        $this->prepareDate();
        $this->ensureDateExists();
    }


    private function prepareServiceIds(): void
    {
        if (!$this->has('service_ids')) {
            return;
        }
        $serviceIds = $this->input('service_ids');
        if (is_string($serviceIds) && str_starts_with($serviceIds, '[')) {
            $decoded = json_decode($serviceIds, true);
            if (is_array($decoded)) {
                $this->merge(['service_ids' => $decoded]);
                return;
            }
        }


        if (is_string($serviceIds) && str_contains($serviceIds, ',')) {
            $ids = explode(',', $serviceIds);
            $ids = array_map('intval', array_map('trim', $ids));
            $this->merge(['service_ids' => $ids]);
            return;
        }
        if (is_string($serviceIds) && is_numeric($serviceIds)) {
            $this->merge(['service_ids' => [(int)$serviceIds]]);
            return;
        }
        if (is_array($serviceIds)) {
            $this->merge([
                'service_ids' => array_map('intval', $serviceIds)
            ]);
        }
    }
    private function prepareDate(): void
    {

        if ($this->has('appointment_date') && !$this->has('day')) {
            $date = Carbon::parse($this->appointment_date);
            $this->merge([
                'day' => strtolower($date->format('l')),
            ]);
        }
        if ($this->has('day') && !$this->has('appointment_date')) {
            $this->merge([
                'appointment_date' => $this->getNextDateFromDay($this->day),
            ]);
        }
    }
    private function getNextDateFromDay(string $day): string
    {
        $daysMap = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
        ];

        $today = Carbon::today();
        $targetDay = $daysMap[$day];
        $currentDay = $today->dayOfWeek;

        if ($targetDay > $currentDay) {
            $date = $today->copy()->addDays($targetDay - $currentDay);
        } elseif ($targetDay < $currentDay) {
            $date = $today->copy()->addDays(7 - ($currentDay - $targetDay));
        } else {
            $date = $today->copy()->addDays(7);
        }

        return $date->format('Y-m-d');
    }
    private function ensureDateExists(): void
    {
        if (!$this->has('appointment_date') && !$this->has('day')) {
            $this->merge([
                'day' => strtolower(Carbon::now()->format('l')),
            ]);
        }
    }
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
            'service_ids.*.exists' => 'إحدى الخدمات المختارة غير موجودة',

            // رسائل التاريخ والوقت
            'appointment_date.date' => 'صيغة التاريخ غير صحيحة',
            'appointment_date.after_or_equal' => 'لا يمكن حجز موعد في تاريخ سابق',
            'day.in' => 'اليوم غير صالح',
            'time.required' => 'يجب اختيار الوقت',
            'time.date_format' => 'صيغة الوقت غير صحيحة (مثال: 09:00 أو 14:30)',

            // رسائل الملاحظات
            'notes.max' => 'الملاحظات لا يجب أن تتجاوز 500 حرف',
        ];
    }
}
