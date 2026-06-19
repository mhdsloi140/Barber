<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 - طلبات كثيرة</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #fdf4ff 0%, #f3e8ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        .error-title {
            font-size: 2rem;
            color: #1e293b;
            margin: 1rem 0;
        }
        .error-message {
            color: #64748b;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        .btn-home {
            display: inline-block;
            padding: 0.8rem 2.5rem;
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            color: white;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3);
        }
        .retry-after {
            font-size: 0.9rem;
            color: #7c3aed;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">429</div>
        <h1 class="error-title">طلبات كثيرة جداً</h1>
        <p class="error-message">لقد قمت بإرسال عدد كبير من الطلبات. يرجى الانتظار قليلاً ثم المحاولة مرة أخرى</p>
        <div class="retry-after"> حاول مرة أخرى بعد 60 ثانية</div>
        <br>
        <a href="{{ url('/') }}" class="btn-home">
            <i class="fas fa-home"></i> العودة إلى الرئيسية
        </a>
    </div>
</body>
</html>
