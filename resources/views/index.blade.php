<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>نعيما | تسجيل الدخول</title>
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Nucleo Icons -->
  <link href="./assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="./assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- Main Styling -->
  <link href="./assets/css/soft-ui-dashboard-tailwind.css?v=1.0.5" rel="stylesheet" />
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
      position: relative;
    }

    /* خلفية بنفسجية متدرجة مثل النافبار */
    .auth-wrapper {
      width: 100%;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      background: linear-gradient(135deg, #4a0e6e 0%, #9b30ff 100%);
      position: relative;
      overflow: auto;
    }

    /* بطاقة تسجيل الدخول أنيقة */
    .auth-card {
      max-width: 460px;
      width: 100%;
      background: white;
      border-radius: 2rem;
      box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.3);
      padding: 2rem 2rem 2.2rem;
      transition: transform 0.2s;
      backdrop-filter: blur(2px);
    }
    .auth-card:hover {
      transform: translateY(-5px);
    }
    .auth-header {
      text-align: center;
      margin-bottom: 1.8rem;
    }
    .auth-header .logo-icon {
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
    .auth-header .logo-icon i {
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
      margin-bottom: 0.3rem;
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
      transition: all 0.2s;
      background: #f9fafb;
      font-family: 'Cairo', sans-serif;
    }
    .input-icon input:focus {
      outline: none;
      border-color: #8b5cf6;
      box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
      background: white;
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
    .error-message {
      background: #fee2e2;
      color: #dc2626;
      padding: 0.6rem;
      border-radius: 1rem;
      font-size: 0.75rem;
      text-align: center;
      margin-bottom: 1rem;
      display: none;
    }
    .success-message {
      background: #dcfce7;
      color: #16a34a;
      padding: 0.6rem;
      border-radius: 1rem;
      font-size: 0.75rem;
      text-align: center;
      margin-bottom: 1rem;
    }
    @media (max-width: 480px) {
      .auth-card { padding: 1.5rem; }
      .auth-header h2 { font-size: 1.5rem; }
    }
  </style>
</head>
<body>
  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="auth-header">
        <div class="logo-icon">
          <i class="fas fa-crown"></i>
        </div>
        <h2>نعيما</h2>
        <p>مرحباً بك، قم بتسجيل الدخول للوصول إلى لوحة التحكم</p>
      </div>

      <div id="loginErrorMessage" class="error-message"></div>

      <form id="loginForm">
        <div class="input-group">
          <label>البريد الإلكتروني</label>
          <div class="input-icon">
            <i class="fas fa-envelope"></i>
            <input type="email" id="loginEmail" placeholder="example@naima.com" required>
          </div>
        </div>
        <div class="input-group">
          <label>كلمة المرور</label>
          <div class="input-icon">
            <i class="fas fa-lock"></i>
            <input type="password" id="loginPassword" placeholder="••••••••" required>
          </div>
        </div>
        <button type="submit" class="auth-btn">دخول <i class="fas fa-arrow-left mr-1"></i></button>
      </form>


     
    </div>
  </div>

  <script>
    // محاكاة بيانات المستخدم (للتكامل مع Laravel لاحقاً)
    const validUser = {
      email: "admin@naima.com",
      password: "123456"
    };

    document.getElementById('loginForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      const errorDiv = document.getElementById('loginErrorMessage');

      if (email === validUser.email && password === validUser.password) {
        // محاكاة نجاح الدخول: تخزين توكن بسيط و التوجيه للوحة التحكم الرئيسية
        localStorage.setItem('auth_token', 'dummy_token_naima');
        sessionStorage.setItem('user_logged', 'true');
        window.location.href = 'dashboard.html';  // الصفحة الرئيسية للوحة التحكم (التي صممناها سابقاً)
      } else {
        errorDiv.style.display = 'block';
        errorDiv.innerText = '❌ البريد الإلكتروني أو كلمة المرور غير صحيحة. حاول مرة أخرى.';
        setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
      }
    });
  </script>
</body>
</html>
