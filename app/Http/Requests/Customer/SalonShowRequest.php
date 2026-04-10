<?php
// app/Http/Requests/Customer/SalonShowRequest.php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SalonShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
        ];
    }
}
