<?php
// app/Http/Requests/Customer/SalonSearchRequest.php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SalonSearchRequest extends FormRequest
{

/*******  889db317-0bd2-467d-95f7-27d0edaddbef  *******/    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.numeric' => 'خط العرض يجب أن يكون رقماً',
            'latitude.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقماً',
            'longitude.between' => 'خط الطول يجب أن يكون بين -180 و 180',
            'per_page.min' => 'عدد النتائج يجب أن يكون 1 على الأقل',
            'per_page.max' => 'عدد النتائج لا يجب أن يتجاوز 50',
        ];
    }
}
