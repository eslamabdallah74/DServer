<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل الدخول — لوحة إدارة ديسيت أونلاين</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #07090e;
      --card-bg: #121824;
      --card-border: rgba(245, 158, 11, 0.2);
      --gold: #f59e0b;
      --gold-hover: #d97706;
      --text-main: #f3f4f6;
      --text-muted: #94a3b8;
      --red: #ef4444;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', system-ui, sans-serif; }
    body {
      background: radial-gradient(circle at top, #1a1528, var(--bg-dark) 70%);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-container {
      width: 100%;
      max-width: 440px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 36px 32px;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
      position: relative;
      overflow: hidden;
    }
    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #f59e0b, #ef4444, #8b5cf6);
    }
    .brand-header {
      text-align: center;
      margin-bottom: 28px;
    }
    .brand-icon {
      width: 64px;
      height: 64px;
      background: linear-gradient(135deg, #f59e0b, #b45309);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #07090e;
      font-size: 28px;
      margin-bottom: 14px;
      box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
    }
    .brand-header h1 {
      font-size: 22px;
      font-weight: 800;
      color: #fff;
    }
    .brand-header p {
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 4px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 700;
      color: var(--text-muted);
      margin-bottom: 8px;
    }
    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .input-wrapper i {
      position: absolute;
      right: 14px;
      color: var(--text-muted);
      font-size: 15px;
    }
    .form-control {
      width: 100%;
      padding: 12px 42px 12px 14px;
      background: rgba(0, 0, 0, 0.4);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 10px;
      color: #fff;
      font-size: 14px;
      transition: all 0.2s;
    }
    .form-control:focus {
      outline: none;
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
    }
    .remember-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      font-size: 13px;
    }
    .remember-label {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-muted);
      cursor: pointer;
    }
    .btn-submit {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #07090e;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 800;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
      transition: all 0.2s;
    }
    .btn-submit:hover {
      opacity: 0.95;
      transform: translateY(-1px);
    }
    .alert-error {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #f87171;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="brand-header">
      <div class="brand-icon"><i class="fa-solid fa-masks-theater"></i></div>
      <h1>ديسيت أونلاين</h1>
      <p>بوابة إدارة الخادم والتحليلات المباشرة</p>
    </div>

    @if ($errors->any())
      <div class="alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>{{ $errors->first() }}</span>
      </div>
    @endif

    <form action="{{ route('admin.login.submit') }}" method="POST">
      @csrf
      <div class="form-group">
        <label class="form-label">اسم المستخدم أو البريد الإلكتروني</label>
        <div class="input-wrapper">
          <i class="fa-solid fa-user"></i>
          <input type="text" name="login" class="form-control" value="{{ old('login') }}" required autofocus placeholder="admin أو بريدك">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">كلمة المرور</label>
        <div class="input-wrapper">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" class="form-control" required placeholder="••••••••">
        </div>
      </div>

      <div class="remember-row">
        <label class="remember-label">
          <input type="checkbox" name="remember" value="1">
          <span>تذكرني على هذا الجهاز</span>
        </label>
      </div>

      <button type="submit" class="btn-submit">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span>تسجيل الدخول</span>
      </button>
    </form>
  </div>
</body>
</html>
