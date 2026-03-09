<?php
// app/Http/Requests/RegisterRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSalon = $this->has('salon_name') || $this->has('owner_name') || $this->has('salon_location');

        if ($isSalon) {
            return [
                'owner_name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'unique:users,phone', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'salon_name' => ['required', 'string', 'max:255'],
                'salon_location' => ['required', 'string', 'max:255'],
                'salon_phone' => ['nullable', 'string', 'max:15'],
                'salon_description' => ['nullable', 'string', 'max:500'],
                'latitude' => ['nullable', 'numeric'],
                'longitude' => ['nullable', 'numeric'],
            ];
        } else {
            return [
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'unique:users,phone', 'regex:/^[0-9]+$/', 'min:10', 'max:15'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ];
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الزبون مطلوب',
            'owner_name.required' => 'اسم صاحب الصالون مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',
            'phone.min' => 'رقم الهاتف يجب أن يكون 10 أرقام على الأقل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'salon_name.required' => 'اسم الصالون مطلوب',
            'salon_location.required' => 'موقع الصالون مطلوب',
        ];
    }

    public function getUserType(): string
    {
        return $this->has('salon_name') ? 'salon_owner' : 'customer';
    }

    public function getSalonData(): array
    {
        return [
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'salon_name' => $this->salon_name,
            'salon_location' => $this->salon_location,
            'salon_phone' => $this->salon_phone,
            'salon_description' => $this->salon_description,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function getCustomerData(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
        ];
    }
}
