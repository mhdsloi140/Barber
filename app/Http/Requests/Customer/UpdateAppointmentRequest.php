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

    /**
     * قواعد التحقق من صحة البيانات
     */
    public function rules(): array
    {
        return [
            // تاريخ الموعد - يمكن أن يكون تاريخ محدد أو يوم من أيام الأسبوع
            'appointment_date' => ['nullable', 'date', 'after_or_equal:today'],
            'day' => ['nullable', 'string', Rule::in(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])],

            // وقت الموعد
            'time' => ['nullable', 'date_format:H:i'],

            // الخدمات - مصفوفة من معرفات الخدمات
            'service_ids' => ['nullable', 'array', 'min:1'],
            'service_ids.*' => ['exists:barber_services,id'],

            // ملاحظات إضافية

        ];
    }

    /**
     * رسائل الخطأ المخصصة
     */
    public function messages(): array
    {
        return [
            // تاريخ الموعد
            'appointment_date.date' => 'صيغة التاريخ غير صحيحة',
            'appointment_date.after_or_equal' => 'لا يمكن تحديد تاريخ مضى، يرجى اختيار تاريخ اليوم أو مستقبلي',

            // اليوم
            'day.in' => 'اليوم غير صالح، يرجى اختيار يوم صحيح (sunday, monday, ...)',

            // الوقت
            'time.date_format' => 'صيغة الوقت غير صحيحة، يرجى استخدام صيغة H:i (مثال: 14:30)',

            // الخدمات
            'service_ids.array' => 'يجب أن تكون الخدمات مصفوفة',
            'service_ids.min' => 'يجب اختيار خدمة واحدة على الأقل',
            'service_ids.*.exists' => 'إحدى الخدمات المحددة غير موجودة',

            // الملاحظات
            'notes.max' => 'الملاحظات لا يجب أن تتجاوز 500 حرف',
        ];
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        // إذا تم إرسال اليوم وليس التاريخ، نحوله إلى تاريخ
        if ($this->has('day') && !$this->has('appointment_date')) {
            // سيتم التعامل مع تحويل اليوم إلى تاريخ في الـ Service
        }

        // تنظيف الوقت (إزالة الثواني إذا وجدت)
        if ($this->has('time')) {
            $time = $this->input('time');
            // إذا كان الوقت يحتوي على ثواني (H:i:s)، نقوم بقصه إلى H:i
            if (strlen($time) > 5 && substr_count($time, ':') == 2) {
                $this->merge([
                    'time' => substr($time, 0, 5)
                ]);
            }
        }
    }

    /**
     * الحصول على البيانات المعالجة للتحديث
     */
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

    /**
     * التحقق من وجود أي بيانات للتحديث
     */
    public function hasAnyUpdateData(): bool
    {
        return $this->has('appointment_date')
            || $this->has('day')
            || $this->has('time')
            || $this->has('service_ids')
            || $this->has('notes');
    }
}
