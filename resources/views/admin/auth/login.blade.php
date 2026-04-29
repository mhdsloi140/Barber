<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نعيما | تسجيل الدخول</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="{{ asset('assets/css/nucleo-icons.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/soft-ui-dashboard-tailwind.css') }}" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7ff 0%, #f0f2fa 100%);
            font-family: 'Cairo', 'Open Sans', sans-serif !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-wrapper {
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #4a0e6e 0%, #9b30ff 100%);
        }

        .auth-card {
            max-width: 460px;
            width: 100%;
            background: white;
            border-radius: 2rem;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            transition: transform 0.2s;
        }

        .auth-card:hover {
            transform: translateY(-5px);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .logo-icon {
            background: linear-gradient(125deg, #7c3aed, #db2777);
            width: 70px;
            height: 70px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 10px 20px rgba(108, 43, 217, 0.3);
        }

        .logo-icon i {
            font-size: 2.4rem;
            color: white;
        }

        .auth-header h2 {
            font-size: 1.9rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4a0e6e, #9b30ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .auth-header p {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .input-group {
            margin-bottom: 1.3rem;
        }

        .input-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
            margin-right: 0.3rem;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1rem;
        }

        .input-icon input {
            width: 100%;
            padding: 0.9rem 2.5rem 0.9rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 1.2rem;
            font-size: 0.9rem;
            background: #f9fafb;
            font-family: 'Cairo', sans-serif;
            transition: all 0.2s;
        }

        .input-icon input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            background: white;
        }

        /* تنسيق الحقل الذي به خطأ */
        .input-icon input.is-invalid {
            border-color: #dc2626;
            background-color: #fff5f5;
        }

        /* رسالة الخطأ فقط - بدون أيقونات */
        .error-text {
            color: #dc2626;
            font-size: 0.7rem;
            margin-top: 0.4rem;
            margin-right: 0.5rem;
        }

        .auth-btn {
            background: linear-gradient(95deg, #7c3aed, #c2418c);
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 2rem;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 0.5rem;
            font-family: 'Cairo', sans-serif;
        }

        .auth-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 18px rgba(124, 58, 237, 0.4);
        }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .auth-footer a {
            color: #7c3aed;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        /* صندوق الأخطاء العامة - بدون أيقونات */
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            border-right: 3px solid #dc2626;
        }

        .alert-danger div {
            margin-bottom: 0.25rem;
        }

        .alert-danger div:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 1.5rem;
            }

            .auth-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-icon"> <i class="fas fa-cut text-purple-300 text-xl"></i></div>
                <h2>نعيما</h2>
                <p>مرحباً بك، قم بتسجيل الدخول للوصول إلى لوحة التحكم</p>
            </div>

            {{-- عرض رسائل الأخطاء من الخادم --}}
            @if ($errors->any())
            <div class="alert-danger">
                @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
                @endforeach
            </div>
            @endif

            {{-- رسالة خطأ عامة من الجلسة --}}
            @if (session('error'))
            <div class="alert-danger">
                {{ session('error') }}
            </div>
            @endif

            <form action="{{ route('admin.login') }}" method="POST">
                @csrf

                <div class="input-group">
                    <label>رقم الهاتف</label>
                    <div class="input-icon">
                        <i class="fas fa-phone-alt"></i>
                        <input type="tel" name="phone" value="{{ old('phone') }}"
                               class="@error('phone') is-invalid @enderror"
                               placeholder="05xxxxxxxx">
                    </div>
                    @error('phone')
                    <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <div class="input-group">
                    <label>كلمة المرور</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password"
                               class="@error('password') is-invalid @enderror"
                               placeholder="••••••••" >
                    </div>
                    @error('password')
                    <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="auth-btn">
                    دخول <i class="fas fa-arrow-left mr-1"></i>
                </button>
            </form>


        </div>
    </div>
</body>

</html>
