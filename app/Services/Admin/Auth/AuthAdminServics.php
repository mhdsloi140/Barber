<?php
namespace App\Services\Admin\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AuthAdminServics
{
    public function login($data)
    {
        // 1. البحث عن المستخدم برقم الهاتف
        $user = User::where('phone', $data['phone'])->first();

        // 2. التحقق: هل رقم الهاتف موجود؟
        if (!$user) {
            Log::warning('محاولة دخول برقم هاتف غير موجود', ['phone' => $data['phone']]);
            return [
                'status' => false,
                'message' => 'رقم الهاتف غير موجود',
                'code' => 'phone_not_found'
            ];
        }

        // 3. التحقق: هل يمتلك صلاحية admin؟
        // افترض أن لديك عمود 'role' أو 'is_admin' في جدول users
        // يمكنك تعديل الشرط حسب هيكل قاعدة البيانات لديك

        // الطريقة الأولى: إذا كان لديك عمود role
        if ($user->role !== 'admin' && $user->role !== 'مدير') {
            Log::warning('محاولة دخول من مستخدم بدون صلاحية admin', [
                'phone' => $data['phone'],
                'role' => $user->role ?? 'غير محدد'
            ]);
            return [
                'status' => false,
                'message' => 'ليس لديك صلاحية للدخول إلى لوحة التحكم',
                'code' => 'not_admin'
            ];
        }

        // الطريقة الثانية: إذا كان لديك عمود is_admin (boolean)
        // if (!$user->is_admin) {
        //     return [
        //         'status' => false,
        //         'message' => 'ليس لديك صلاحية للدخول إلى لوحة التحكم'
        //     ];
        // }

        // 4. التحقق: هل كلمة المرور صحيحة؟
        if (!Hash::check($data['password'], $user->password)) {

            return [
                'status' => false,
                'message' => 'خطأ في كلمة المرور أو رقم الهاتف',
                'code' => 'invalid_password'
            ];
        }

        // 5. جميع التحقيات ناجحة - تحديث آخر تسجيل دخول
        // $user->last_login = Carbon::now();
        // $user->save();



        return [
            'status' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => $user,
            'code' => 'success'
        ];
    }
}
