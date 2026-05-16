<?php

namespace App\Http\Requests\Customer;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class FavoriteBarberRequest extends FormRequest
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
                 'barber_id' => ['required', 'exists:users,id', function ($attribute, $value, $fail) {
                $barber = User::find($value);
                if (!$barber || !$barber->hasRole('barber')) {
                    $fail('المستخدم المحدد ليس حلاقاً');
                }
            }],
        ];
    }
     public function messages(): array
    {
        return [
            'barber_id.required' => 'معرف الحلاق مطلوب',
            'barber_id.exists' => 'الحلاق غير موجود',
        ];
    }
}
