<?php
// app/Http/Requests/SalonRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('salon_owner');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $salon = auth()->user()->ownedSalon;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:15', 'regex:/^[0-9]+$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'اسم الصالون يجب أن يكون نصاً',
            'name.max' => 'اسم الصالون لا يجب أن يتجاوز 255 حرف',
            'address.string' => 'العنوان يجب أن يكون نصاً',
            'address.max' => 'العنوان لا يجب أن يتجاوز 255 حرف',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180',
            'phone.max' => 'رقم الهاتف لا يجب أن يتجاوز 15 رقم',
            'phone.regex' => 'رقم الهاتف يجب أن يحتوي على أرقام فقط',
        ];
    }

    /**
     * Get the data to update.
     */
    public function getUpdateData(): array
    {
        return [
            'name' => $this->input('name'),
            'address' => $this->input('address'),
            'latitude' => $this->input('latitude'),
            'longitude' => $this->input('longitude'),
            'phone' => $this->input('phone'),
        ];
    }

    /**
     * Check if there is any data to update.
     */
    public function hasDataToUpdate(): bool
    {
        return $this->has('name') || $this->has('address') || $this->has('latitude') ||
               $this->has('longitude') || $this->has('phone');
    }
}
