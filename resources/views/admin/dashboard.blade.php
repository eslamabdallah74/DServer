@extends('admin.layout')

@section('title', 'نظرة عامة والبيانات الحية')
@section('header_title', 'لوحة القيادة والمراقبة المباشرة')

@section('content')
<!-- KPI Stat Grid -->
<div class="stat-grid">
  <div class="card" style="border-top: 3px solid #f59e0b;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <div>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">إجمالي اللاعبين المسجلين</div>
        <div style="font-size: 32px; font-weight: 900; color: #fff;">{{ number_format($totalPlayers) }}</div>
      </div>
      <div style="width: 44px; height: 44px; background: rgba(245,158,11,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gold); font-size: 20px;">
        <i class="fa-solid fa-users"></i>
      </div>
    </div>
  </div>

  <div class="card" style="border-top: 3px solid #10b981;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <div>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">الغرف الحية النشطة الآن</div>
        <div style="font-size: 32px; font-weight: 900; color: #34d399;">{{ count($activeRooms) }}</div>
      </div>
      <div style="width: 44px; height: 44px; background: rgba(16,185,129,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #10b981; font-size: 20px;">
        <i class="fa-solid fa-gamepad"></i>
      </div>
    </div>
  </div>

  <div class="card" style="border-top: 3px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <div>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">المباريات المكتملة</div>
        <div style="font-size: 32px; font-weight: 900; color: #60a5fa;">{{ number_format($totalMatches) }}</div>
      </div>
      <div style="width: 44px; height: 44px; background: rgba(59,130,246,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 20px;">
        <i class="fa-solid fa-trophy"></i>
      </div>
    </div>
  </div>

  <div class="card" style="border-top: 3px solid #8b5cf6;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <div>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">متوسط تقييم التطبيق</div>
        <div style="font-size: 32px; font-weight: 900; color: #a78bfa;">{{ $avgRating }} ⭐</div>
      </div>
      <div style="width: 44px; height: 44px; background: rgba(139,92,246,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-size: 20px;">
        <i class="fa-solid fa-star"></i>
      </div>
    </div>
  </div>

  <div class="card" style="border-top: 3px solid #ef4444;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <div>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">بلاغات الأخطاء</div>
        <div style="font-size: 32px; font-weight: 900; color: #f87171;">{{ number_format($totalIssues) }}</div>
      </div>
      <div style="width: 44px; height: 44px; background: rgba(239,68,68,0.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 20px;">
        <i class="fa-solid fa-bug"></i>
      </div>
    </div>
  </div>
</div>

<!-- Live Games Section & Victory Stats -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 28px;">
  <!-- Live Active Rooms -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-tower-broadcast"></i> الغرف الحية النشطة على السيرفر</h3>
      <a href="{{ route('admin.rooms') }}" class="btn btn-outline" style="font-size: 12px; padding: 6px 12px;">عرض الكل والتحكم المباشر &larr;</a>
    </div>

    @if(count($activeRooms) === 0)
      <div style="text-align: center; padding: 36px 16px; color: var(--text-muted);">
        <i class="fa-solid fa-moon" style="font-size: 36px; margin-bottom: 12px; color: var(--gold); opacity: 0.5;"></i>
        <p style="font-size: 14px;">لا توجد غرف نشطة حالياً. عند قيام اللاعبين بإنشاء غرف عبر اللعبة ستظهر هنا فورياً.</p>
      </div>
    @else
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>كود الغرفة</th>
              <th>المرحلة الحالية</th>
              <th>اللاعبين</th>
              <th>البوتات</th>
              <th>الجولة</th>
              <th>إجراء سريع</th>
            </tr>
          </thead>
          <tbody>
            @foreach(array_slice($activeRooms, 0, 5) as $room)
            <tr>
              <td><span class="badge badge-gold" style="font-size: 13px; letter-spacing: 1px;">{{ $room['roomCode'] }}</span></td>
              <td>
                @php
                  $phase = $room['phase'] ?? 'LOBBY';
                  $badgeClass = match($phase) {
                    'LOBBY' => 'badge-blue',
                    'NIGHT' => 'badge-purple',
                    'VOTING' => 'badge-red',
                    'MORNING', 'DAY' => 'badge-green',
                    default => 'badge-gold',
                  };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $phase }}</span>
              </td>
              <td>{{ count(array_filter($room['players'] ?? [], fn($p) => !($p['isBot'] ?? false))) }} لاعبين</td>
              <td>{{ count(array_filter($room['players'] ?? [], fn($p) => $p['isBot'] ?? false)) }} بوتات</td>
              <td>جولة {{ $room['round'] ?? 0 }}</td>
              <td>
                <div style="display: flex; gap: 6px;">
                  <form action="{{ route('admin.rooms.nextPhase', $room['roomCode']) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="padding: 4px 10px; font-size: 11px;" title="تمرير المرحلة">
                      <i class="fa-solid fa-forward-step"></i>
                    </button>
                  </form>
                  <form action="{{ route('admin.rooms.destroy', $room['roomCode']) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من إنهاء الغرفة؟');">
                    @csrf
                    <button type="submit" class="btn btn-red" style="padding: 4px 10px; font-size: 11px;" title="إغلاق الغرفة">
                      <i class="fa-solid fa-power-off"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <!-- Victory Distribution Widget -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-chart-pie"></i> إحصائيات الفوز في اللعبة</h3>
    </div>

    @php
      $sumWins = max(1, $kingdomWins + $shadowWins + $neutralWins);
      $kPct = round(($kingdomWins / $sumWins) * 100);
      $sPct = round(($shadowWins / $sumWins) * 100);
      $nPct = round(($neutralWins / $sumWins) * 100);
    @endphp

    <div style="margin-bottom: 18px;">
      <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
        <span style="color: #60a5fa; font-weight: 700;"><i class="fa-solid fa-crown"></i> فريق المملكة</span>
        <span style="font-weight: 800;">{{ $kingdomWins }} فوز ({{ $kPct }}%)</span>
      </div>
      <div style="height: 10px; background: rgba(255,255,255,0.06); border-radius: 5px; overflow: hidden;">
        <div style="width: {{ $kPct }}%; height: 100%; background: #3b82f6;"></div>
      </div>
    </div>

    <div style="margin-bottom: 18px;">
      <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
        <span style="color: #f87171; font-weight: 700;"><i class="fa-solid fa-skull"></i> فريق الظلال</span>
        <span style="font-weight: 800;">{{ $shadowWins }} فوز ({{ $sPct }}%)</span>
      </div>
      <div style="height: 10px; background: rgba(255,255,255,0.06); border-radius: 5px; overflow: hidden;">
        <div style="width: {{ $sPct }}%; height: 100%; background: #ef4444;"></div>
      </div>
    </div>

    <div style="margin-bottom: 18px;">
      <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
        <span style="color: #a78bfa; font-weight: 700;"><i class="fa-solid fa-masks-theater"></i> الفريق المحايد (المخادع)</span>
        <span style="font-weight: 800;">{{ $neutralWins }} فوز ({{ $nPct }}%)</span>
      </div>
      <div style="height: 10px; background: rgba(255,255,255,0.06); border-radius: 5px; overflow: hidden;">
        <div style="width: {{ $nPct }}%; height: 100%; background: #8b5cf6;"></div>
      </div>
    </div>

    <div style="text-align: center; margin-top: 24px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.06);">
      <a href="{{ route('admin.analytics') }}" class="btn btn-outline" style="width: 100%; justify-content: center; font-size: 12px;">
        <i class="fa-solid fa-square-poll-vertical"></i> تفاصيل التحليلات الكاملة
      </a>
    </div>
  </div>
</div>

<!-- Recent Registered Players & Issues -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
  <!-- Recent Players -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-user-plus"></i> أحدث اللاعبين المتصلين</h3>
      <a href="{{ route('admin.players') }}" class="btn btn-outline" style="font-size: 11px; padding: 4px 10px;">إدارة اللاعبين &larr;</a>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>الاسم</th>
            <th>العملات</th>
            <th>الانتصارات</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentPlayers as $p)
          <tr>
            <td>
              <div style="font-weight: 700;">{{ $p->nickname }}</div>
              <code style="font-size: 10px; color: var(--text-muted);">{{ $p->player_id }}</code>
            </td>
            <td>💰 {{ number_format($p->coins) }}</td>
            <td>🏆 {{ $p->stats_wins }} / {{ $p->stats_matches }}</td>
            <td>
              @if($p->is_banned)
                <span class="badge badge-red">محظور</span>
              @else
                <span class="badge badge-green">نشط</span>
              @endif
            </td>
          </tr>
          @empty
          <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">لا يوجد لاعبين مسجلين بعد.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Issues -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> أحدث بلاغات الأخطاء</h3>
      <a href="{{ route('admin.issues') }}" class="btn btn-outline" style="font-size: 11px; padding: 4px 10px;">عرض الكل &larr;</a>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>الخطورة</th>
            <th>الرسالة</th>
            <th>الصفحة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentIssues as $issue)
          <tr>
            <td>
              @php
                $sev = strtolower($issue->severity);
                $bClass = $sev === 'fatal' ? 'badge-red' : ($sev === 'error' ? 'badge-red' : 'badge-gold');
              @endphp
              <span class="badge {{ $bClass }}">{{ strtoupper($issue->severity) }}</span>
            </td>
            <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              {{ $issue->message }}
            </td>
            <td><code style="font-size: 11px;">{{ $issue->page ?: 'Global' }}</code></td>
            <td style="font-size: 11px; color: var(--text-muted);">{{ $issue->created_at->diffForHumans() }}</td>
          </tr>
          @empty
          <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">لا توجد أي أخطاء مسجلة! النظام سليم.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
