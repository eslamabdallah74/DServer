<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'لوحة التحكم') — ديسيت أونلاين</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #07090e;
      --sidebar-bg: #0d111a;
      --card-bg: #121824;
      --card-border: rgba(245, 158, 11, 0.16);
      --gold: #f59e0b;
      --gold-hover: #d97706;
      --gold-light: rgba(245, 158, 11, 0.12);
      --text-main: #f3f4f6;
      --text-muted: #94a3b8;
      --red: #ef4444;
      --green: #10b981;
      --blue: #3b82f6;
      --purple: #8b5cf6;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', system-ui, sans-serif; }
    body { background-color: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; }

    /* Sidebar */
    .sidebar {
      width: 260px;
      background: var(--sidebar-bg);
      border-left: 1px solid var(--card-border);
      padding: 24px 16px;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding-bottom: 24px;
      margin-bottom: 20px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .brand-icon {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, #f59e0b, #b45309);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #07090e;
      font-size: 20px;
    }
    .brand-text h2 { font-size: 17px; font-weight: 800; color: #fff; }
    .brand-text span { font-size: 11px; color: var(--gold); letter-spacing: 0.5px; }

    .nav-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .nav-item a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 14px;
      color: var(--text-muted);
      text-decoration: none;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .nav-item a:hover, .nav-item.active a {
      background: var(--gold-light);
      color: var(--gold);
      border: 1px solid rgba(245, 158, 11, 0.25);
    }
    .nav-item a i { width: 20px; text-align: center; font-size: 16px; }

    /* Content Area */
    .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; }
    .topbar {
      height: 70px;
      background: var(--sidebar-bg);
      border-bottom: 1px solid var(--card-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 32px;
    }
    .page-title { font-size: 20px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px; }
    .topbar-actions { display: flex; align-items: center; gap: 14px; }

    .content-body { padding: 32px; overflow-y: auto; flex: 1; }

    /* Buttons & Badges */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      border: none;
      transition: all 0.2s;
    }
    .btn-gold { background: linear-gradient(135deg, #f59e0b, #d97706); color: #07090e; }
    .btn-gold:hover { opacity: 0.92; transform: translateY(-1px); }
    .btn-red { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
    .btn-red:hover { background: #ef4444; color: #fff; }
    .btn-blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
    .btn-blue:hover { background: #3b82f6; color: #fff; }
    .btn-outline { background: transparent; color: var(--text-muted); border: 1px solid rgba(255,255,255,0.1); }
    .btn-outline:hover { color: #fff; border-color: rgba(255,255,255,0.25); }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
    }
    .badge-gold { background: var(--gold-light); color: var(--gold); border: 1px solid rgba(245, 158, 11, 0.3); }
    .badge-green { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
    .badge-red { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    .badge-blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
    .badge-purple { background: rgba(139, 92, 246, 0.15); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.3); }

    /* Cards & Stats */
    .stat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 20px;
      margin-bottom: 28px;
    }
    .card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 14px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    }
    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }
    .card-title { font-size: 15px; font-weight: 700; color: var(--gold); display: flex; align-items: center; gap: 8px; }

    /* Tables */
    .table-container { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; text-align: right; }
    th {
      padding: 13px 16px;
      font-size: 12px;
      color: var(--text-muted);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      font-weight: 700;
      text-transform: uppercase;
    }
    td {
      padding: 14px 16px;
      font-size: 13px;
      border-bottom: 1px solid rgba(255,255,255,0.04);
      color: #e2e8f0;
      vertical-align: middle;
    }
    tr:hover td { background: rgba(255,255,255,0.02); }

    /* Alerts */
    .alert {
      padding: 14px 20px;
      border-radius: 10px;
      margin-bottom: 24px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #34d399; }
    .alert-danger { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.7);
      backdrop-filter: blur(4px);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    .modal.active { display: flex; }
    .modal-box {
      background: var(--card-bg);
      border: 1px solid var(--gold);
      border-radius: 16px;
      width: 90%;
      max-width: 520px;
      padding: 28px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.6);
    }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 13px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; }
    .form-control {
      width: 100%;
      padding: 10px 14px;
      background: rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 8px;
      color: #fff;
      font-size: 14px;
    }
    .form-control:focus { outline: none; border-color: var(--gold); }
  </style>
  @yield('styles')
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-masks-theater"></i></div>
      <div class="brand-text">
        <h2>ديسيت أونلاين</h2>
        <span>SERVER MANAGEMENT</span>
      </div>
    </div>

    <ul class="nav-menu">
      <li class="nav-item {{ request()->is('admin') || request()->is('admin/dashboard') ? 'active' : '' }}">
        <a href="{{ route('admin.dashboard') }}"><i class="fa-solid fa-chart-pie"></i> نظرة عامة</a>
      </li>
      <li class="nav-item {{ request()->is('admin/rooms*') || request()->is('admin/spectator*') ? 'active' : '' }}">
        <a href="{{ route('admin.rooms') }}"><i class="fa-solid fa-gamepad"></i> الغرف الحية والمراقبة</a>
      </li>
      <li class="nav-item {{ request()->is('admin/analytics*') ? 'active' : '' }}">
        <a href="{{ route('admin.analytics') }}"><i class="fa-solid fa-chart-line"></i> تحليلات وتوازن الأدوار</a>
      </li>
      <li class="nav-item {{ request()->is('admin/moderation*') ? 'active' : '' }}">
        <a href="{{ route('admin.moderation') }}"><i class="fa-solid fa-user-shield"></i> مكافحة التحالف والرقابة</a>
      </li>
      <li class="nav-item {{ request()->is('admin/tuning*') ? 'active' : '' }}">
        <a href="{{ route('admin.tuning') }}"><i class="fa-solid fa-sliders"></i> الضبط المباشر (Tuning)</a>
      </li>
      <li class="nav-item {{ request()->is('admin/stress-test*') ? 'active' : '' }}">
        <a href="{{ route('admin.stressTest') }}"><i class="fa-solid fa-microchip"></i> محاكي الضغط والبوتات</a>
      </li>
      <li class="nav-item {{ request()->is('admin/pusher*') ? 'active' : '' }}">
        <a href="{{ route('admin.pusher') }}"><i class="fa-solid fa-satellite-dish"></i> مراقبة استهلاك Pusher</a>
      </li>
      <li class="nav-item {{ request()->is('admin/players*') ? 'active' : '' }}">
        <a href="{{ route('admin.players') }}"><i class="fa-solid fa-users"></i> اللاعبين والعملات</a>
      </li>
      <li class="nav-item {{ request()->is('admin/issues*') ? 'active' : '' }}">
        <a href="{{ route('admin.issues') }}"><i class="fa-solid fa-bug"></i> بلاغات الأخطاء</a>
      </li>
      <li class="nav-item {{ request()->is('admin/feedback*') ? 'active' : '' }}">
        <a href="{{ route('admin.feedback') }}"><i class="fa-solid fa-comments"></i> تقييمات اللاعبين</a>
      </li>
      <li class="nav-item {{ request()->is('admin/audit-logs*') ? 'active' : '' }}">
        <a href="{{ route('admin.auditLogs') }}"><i class="fa-solid fa-shield-halved"></i> سجل العمليات</a>
      </li>
    </ul>

    <div style="padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.06);">
      <form action="{{ route('admin.logout') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center;">
          <i class="fa-solid fa-arrow-right-from-bracket"></i> تسجيل الخروج
        </button>
      </form>
    </div>
  </aside>

  <!-- Main Wrapper -->
  <div class="main-wrapper">
    <header class="topbar">
      <div class="page-title">
        @yield('header_title', 'لوحة التحكم')
      </div>
      <div class="topbar-actions">
        <button type="button" class="btn btn-gold" onclick="openBroadcastModal()">
          <i class="fa-solid fa-bullhorn"></i> إعلان عام للمباراة
        </button>
        <span class="badge badge-gold"><i class="fa-solid fa-user-shield"></i> {{ Auth::user()->username ?? 'المشرف' }}</span>
      </div>
    </header>

    <main class="content-body">
      @if(session('success'))
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          {{ session('success') }}
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger">
          <i class="fa-solid fa-triangle-exclamation"></i>
          {{ session('error') }}
        </div>
      @endif

      @yield('content')
    </main>
  </div>

  <!-- Global Announcement Broadcast Modal -->
  <div class="modal" id="broadcastModal">
    <div class="modal-box">
      <div class="card-header" style="margin-bottom: 14px;">
        <h3 class="card-title"><i class="fa-solid fa-bullhorn"></i> إرسال إعلان عام لجميع اللاعبين</h3>
        <button type="button" onclick="closeBroadcastModal()" style="background: none; border: none; color: #999; font-size: 18px; cursor: pointer;">&times;</button>
      </div>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
        سيتم إرسال هذا الإشعار فوراً عبر قنوات Pusher WebSocket لجميع اللاعبين المتواجدين في اللعبة والغرف ليظهر كشريط تنبيهي مباشر.
      </p>
      <form action="{{ route('admin.broadcast') }}" method="POST">
        @csrf
        <div class="form-group">
          <label class="form-label">نص الإعلان أو التنبيه:</label>
          <textarea name="message" class="form-control" rows="3" placeholder="مثال: مرحباً بكم في جولة ديسيت أونلاين! جاري تحديث الغرف قريباً..." required></textarea>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn btn-outline" onclick="closeBroadcastModal()">إلغاء</button>
          <button type="submit" class="btn btn-gold"><i class="fa-solid fa-paper-plane"></i> إرسال الآن</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openBroadcastModal() {
      document.getElementById('broadcastModal').classList.add('active');
    }
    function closeBroadcastModal() {
      document.getElementById('broadcastModal').classList.remove('active');
    }
  </script>
  @yield('scripts')
</body>
</html>

