<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إدارة اللاعبين — Deceit Server</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --bg-dark: #0a0c10;
      --card-bg: #12161f;
      --border-color: rgba(255, 215, 0, 0.15);
      --gold: #f59e0b;
      --text-main: #f3f4f6;
      --text-muted: #9ca3af;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
    body { background-color: var(--bg-dark); color: var(--text-main); display: flex; min-height: 100vh; }
    .sidebar { width: 240px; background: #0f131a; border-left: 1px solid var(--border-color); padding: 24px 16px; flex-shrink: 0; }
    .sidebar h2 { font-size: 18px; font-weight: 700; color: var(--gold); margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
    .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; border-radius: 8px; margin-bottom: 8px; font-size: 14px; }
    .nav-link:hover, .nav-link.active { background: rgba(245, 158, 11, 0.15); color: var(--gold); }
    .content { flex: 1; padding: 32px; overflow-y: auto; }
    .table-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; }
    table { width: 100%; border-collapse: collapse; text-align: right; }
    th, td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
    th { color: var(--text-muted); }
    .input-coin { width: 80px; padding: 6px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; color: #fff; text-align: center; }
    .btn-sm { padding: 6px 12px; background: var(--gold); color: #000; border: none; border-radius: 4px; font-weight: 700; cursor: pointer; }
    .alert-success { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4); padding: 12px; border-radius: 8px; margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2><i class="fa-solid fa-dragon"></i> Deceit Hub</h2>
    <a href="{{ url('/admin/dashboard') }}" class="nav-link"><i class="fa-solid fa-chart-line"></i> الرئيسية</a>
    <a href="{{ url('/admin/players') }}" class="nav-link active"><i class="fa-solid fa-users"></i> اللاعبين</a>
    <a href="{{ url('/admin/issues') }}" class="nav-link"><i class="fa-solid fa-bug"></i> البلاغات والمشاكل</a>
  </div>

  <div class="content">
    <h1>قائمة إدارة اللاعبين والعملات</h1>
    <br>

    @if(session('success'))
      <div class="alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>معرف الجهاز / اللاعب</th>
            <th>الاسم المستعار</th>
            <th>العملات الحالية</th>
            <th>الشخصيات المملوكة</th>
            <th>تحديث العملات</th>
          </tr>
        </thead>
        <tbody>
          @foreach($players as $p)
          <tr>
            <td><code>{{ $p->player_id }}</code></td>
            <td>{{ $p->nickname }}</td>
            <td>💰 {{ $p->coins }}</td>
            <td>{{ implode(', ', $p->owned_roles) ?: 'الأبرياء' }}</td>
            <td>
              <form action="{{ url('/admin/players/' . $p->player_id . '/coins') }}" method="POST" style="display: flex; gap: 6px;">
                @csrf
                <input type="number" name="coins" value="{{ $p->coins }}" class="input-coin">
                <button type="submit" class="btn-sm">حفظ</button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <br>
      {{ $players->links() }}
    </div>
  </div>
</body>
</html>
