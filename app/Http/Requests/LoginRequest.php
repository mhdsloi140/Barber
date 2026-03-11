<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'min:10', 'max:15', 'exists:users,phone'],
            'password' => ['required', 'min:8', 'max:20']
        ];
    }

    public function message()
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.min' => 'يجب ان يكون 10 ارقام على الاقل',
            'phone.max' => 'يجب ان يكون 10 ارقام',
            'phone.exists' => 'رقم الهاتف غير موجود',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'يجب ان يكون 8 ارقام على الاقل',
            'password.max' => 'يجب ان يكون 20 ارقام',
        ];
    }
    public function afterValidation()
    {
        $data = $this->validated();
        $user = User::where('phone', $data['phone'])->first();


        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'خطأ في كلمة المرور او رقم الهاتف',
            ]);
        }


        return $data;
    }

}
