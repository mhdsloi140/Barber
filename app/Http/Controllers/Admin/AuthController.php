<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Services\Admin\Auth\AuthAdminServics;
use App\Services\Admin\Auth\AuthServics;
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
}
