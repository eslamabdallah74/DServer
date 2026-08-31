<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة التحكم — Deceit Server</title>
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
    body { background-color: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; }
    .sidebar { width: 240px; background: #0f131a; border-left: 1px solid var(--border-color); padding: 24px 16px; flex-shrink: 0; }
    .sidebar h2 { font-size: 18px; font-weight: 700; color: var(--gold); margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
    .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; border-radius: 8px; margin-bottom: 8px; transition: 0.2s; font-size: 14px; }
    .nav-link:hover, .nav-link.active { background: rgba(245, 158, 11, 0.15); color: var(--gold); }
    .content { flex: 1; padding: 32px; overflow-y: auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px; }
    .stat-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; }
    .stat-card .title { font-size: 13px; color: var(--text-muted); margin-bottom: 8px; }
    .stat-card .val { font-size: 32px; font-weight: 800; color: #fff; }
    .table-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .table-card h3 { font-size: 16px; margin-bottom: 16px; color: var(--gold); }
    table { width: 100%; border-collapse: collapse; text-align: right; }
    th, td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
    th { color: var(--text-muted); font-weight: 600; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2><i class="fa-solid fa-dragon"></i> Deceit Hub</h2>
    <a href="{{ url('/admin/dashboard') }}" class="nav-link active"><i class="fa-solid fa-chart-line"></i> الرئيسية</a>
    <a href="{{ url('/admin/players') }}" class="nav-link"><i class="fa-solid fa-users"></i> اللاعبين</a>
    <a href="{{ url('/admin/issues') }}" class="nav-link"><i class="fa-solid fa-bug"></i> البلاغات والمشاكل</a>
  </div>

  <div class="content">
    <div class="header">
      <h1>لوحة التحكم والبيانات</h1>
      <span>مرحباً بك، المشرف</span>
    </div>

    <div class="grid">
      <div class="stat-card">
        <div class="title">إجمالي اللاعبين المسجلين</div>
        <div class="val">{{ $totalPlayers }}</div>
      </div>
      <div class="stat-card">
        <div class="title">المباريات المكتملة</div>
        <div class="val">{{ $totalMatches }}</div>
      </div>
      <div class="stat-card">
        <div class="title">بلاغات المشاكل والاخطاء</div>
        <div class="val">{{ $totalIssues }}</div>
      </div>
    </div>

    <div class="table-card">
      <h3><i class="fa-solid fa-clock-rotate-left"></i> أحدث اللاعبين المسجلين</h3>
      <table>
        <thead>
          <tr>
            <th>معرف اللاعب</th>
            <th>الاسم</th>
            <th>العملات</th>
            <th>الانتصارات</th>
            <th>آخر ظهور</th>
          </tr>
        </thead>
        <tbody>
          @foreach($recentPlayers as $p)
          <tr>
            <td><code>{{ $p->player_id }}</code></td>
            <td>{{ $p->nickname }}</td>
            <td>💰 {{ $p->coins }}</td>
            <td>🏆 {{ $p->stats_wins }}</td>
            <td>{{ $p->updated_at->diffForHumans() }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
