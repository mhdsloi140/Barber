<?php


namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class FavoriteSalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('customer');
    }

    public function rules(): array
    {
        return [
            'salon_id' => ['required', 'exists:salons,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'salon_id.required' => 'معرف الصالون مطلوب',
            'salon_id.exists' => 'الصالون غير موجود',
        ];
    }
}
