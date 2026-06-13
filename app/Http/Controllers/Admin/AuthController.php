<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Http\Requests\Admin\ForgotPasswordRequest;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Services\Admin\Auth\AuthAdminServics;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthAdminServics $autservics)
    {
    }

    public function index()
    {
        return view('admin.auth.login');
    }

    public function login(LoginAdminRequest $request)
    {
        $result = $this->autservics->login($request->validated());

        if ($result['status'] === true) {
            auth()->login($result['user']);
            $request->session()->regenerate();

            return redirect()->route('admin.dashboard')
                           ->with('success', $result['message']);
        }

        return back()->withErrors(['phone' => $result['message']])->onlyInput('phone');
    }

    public function logout(Request $request)
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'تم تسجيل الخروج بنجاح');
    }

    /**
     * عرض صفحة طلب إعادة تعيين كلمة المرور
     */
    public function showForgotPasswordForm()
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * معالجة طلب إعادة تعيين كلمة المرور (إرسال OTP)
     */

public function sendResetOTP(ForgotPasswordRequest $request)
{
    $result = $this->autservics->forgotPassword($request->phone);

    if ($result['status']) {
        if (isset($result['data']['user_id'])) {
            return redirect()->route('admin.reset-password.form', ['userId' => $result['data']['user_id']])
                           ->with('success', $result['message']);
        }
        return back()->withErrors(['phone' => 'حدث خطأ، يرجى المحاولة مرة أخرى']);
    }

    return back()->withErrors(['phone' => $result['message']]);
}

    /**
     * عرض صفحة إدخال OTP وكلمة المرور الجديدة
     */
    public function showResetPasswordForm(int $userId)
    {
        return view('admin.auth.reset-password', compact('userId'));
    }

    /**
     * معالجة إعادة تعيين كلمة المرور
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $result = $this->autservics->resetPassword(
            $request->phone,
            $request->code,
            $request->password
        );

        if ($result['status']) {
            return redirect()->route('admin.login')
                           ->with('success', $result['message']);
        }

        return back()->withErrors(['code' => $result['message']]);
    }
}
