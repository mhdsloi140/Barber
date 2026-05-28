<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('customer');
    }

    public function rules(): array
    {
        return [
            'appointment_date' => ['nullable', 'date', 'after_or_equal:today'],
            'day' => ['nullable', 'string', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],
            'time' => ['nullable', 'date_format:H:i'],
            'service_ids' => ['nullable', 'array', 'min:1'],
            'service_ids.*' => ['exists:barber_services,id'],

        ];
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        // معالجة service_ids إذا أرسلت كـ string
        if ($this->has('service_ids') && is_string($this->input('service_ids'))) {
            $serviceIds = $this->input('service_ids');

            // إذا كانت بصيغة JSON: "[1,2,3]"
            if (str_starts_with($serviceIds, '[') && str_ends_with($serviceIds, ']')) {
                $serviceIds = json_decode($serviceIds, true);
            }
            // إذا كانت بصيغة مفصولة بفواصل: "1,2,3"
            elseif (str_contains($serviceIds, ',')) {
                $serviceIds = array_map('intval', explode(',', $serviceIds));
            }
            // إذا كانت قيمة واحدة: "1"
            else {
                $serviceIds = [(int) $serviceIds];
            }

            // التأكد من أنها مصفوفة أرقام صالحة
            if (is_array($serviceIds) && !empty($serviceIds)) {
                $this->merge([
                    'service_ids' => $serviceIds
                ]);
            }
        }

        // تنظيف الوقت
        if ($this->has('time')) {
            $time = $this->input('time');
            if (strlen($time) > 5 && substr_count($time, ':') == 2) {
                $this->merge(['time' => substr($time, 0, 5)]);
            }
        }
    }

    /**
     * التحقق من صحة البيانات بعد التحضير
     */
    public function withValidator($validator)
    {
        // إذا فشل التحقق من service_ids، اطبع القيمة الأصلية للتصحيح
        if ($validator->errors()->has('service_ids')) {
            $validator->after(function ($validator) {
                $originalValue = $this->input('service_ids');
                logger()->error('service_ids validation failed', [
                    'original_value' => $originalValue,
                    'original_type' => gettype($originalValue),
                    'processed_value' => $this->input('service_ids'),
                ]);
            });
        }
    }

    public function messages(): array
    {
        return [
            'appointment_date.date' => 'صيغة التاريخ غير صحيحة',
            'appointment_date.after_or_equal' => 'لا يمكن تحديد تاريخ مضى',
            'day.in' => 'اليوم غير صالح',
            'time.date_format' => 'صيغة الوقت غير صحيحة',
            'service_ids.array' => 'يجب أن تكون الخدمات مصفوفة (مثال: [1,2,3])',
            'service_ids.min' => 'يجب اختيار خدمة واحدة على الأقل',
            'service_ids.*.exists' => 'إحدى الخدمات المحددة غير موجودة',
            'notes.max' => 'الملاحظات لا يجب أن تتجاوز 500 حرف',
        ];
    }

    public function getUpdateData(): array
    {
        $data = [];

        if ($this->has('appointment_date')) {
            $data['appointment_date'] = $this->input('appointment_date');
        }

        if ($this->has('day')) {
            $data['day'] = $this->input('day');
        }

        if ($this->has('time')) {
            $data['time'] = $this->input('time');
        }

        if ($this->has('service_ids')) {
            $data['service_ids'] = $this->input('service_ids');
        }

        if ($this->has('notes')) {
            $data['notes'] = $this->input('notes');
        }

        return $data;
    }

    public function hasAnyUpdateData(): bool
    {
        return $this->has('appointment_date')
            || $this->has('day')
            || $this->has('time')
            || $this->has('service_ids')
            || $this->has('notes');
    }
}
