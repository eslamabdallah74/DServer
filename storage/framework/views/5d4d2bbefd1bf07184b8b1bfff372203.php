<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل الدخول — Deceit Hub</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --bg-dark: #0a0c10;
      --card-bg: #12161f;
      --border-color: rgba(255, 215, 0, 0.15);
      --gold: #f59e0b;
      --gold-hover: #d97706;
      --text-main: #f3f4f6;
      --text-muted: #9ca3af;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
    body { background-color: var(--bg-dark); color: var(--text-main); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
    .brand { text-align: center; margin-bottom: 30px; }
    .brand i { font-size: 48px; color: var(--gold); margin-bottom: 12px; }
    .brand h1 { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
    .brand p { color: var(--text-muted); font-size: 14px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
    .form-control { width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-size: 14px; outline: none; transition: 0.2s; }
    .form-control:focus { border-color: var(--gold); }
    .btn { width: 100%; padding: 14px; background: var(--gold); color: #000; border: none; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; }
    .btn:hover { background: var(--gold-hover); }
    .alert { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; text-align: center; }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <i class="fa-solid fa-dragon"></i>
      <h1>Deceit Server</h1>
      <p>لوحة التحكم وقاعدة البيانات المركزية</p>
    </div>

    <?php if(session('error')): ?>
      <div class="alert"><?php echo e(session('error')); ?></div>
    <?php endif; ?>

    <form action="<?php echo e(url('/api/auth/login')); ?>" method="POST">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label><i class="fa-solid fa-user-shield"></i> اسم المستخدم / البريد الإلكتروني</label>
        <input type="text" name="username" class="form-control" placeholder="admin@offline.local" required autofocus>
      </div>

      <div class="form-group">
        <label><i class="fa-solid fa-key"></i> كلمة المرور</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn">دخول النظام</button>
    </form>
  </div>
</body>
</html>
<?php /**PATH /home/eslam/work/apps/maskat/server/resources/views/admin/login.blade.php ENDPATH**/ ?>