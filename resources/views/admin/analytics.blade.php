@extends('admin.layout')

@section('title', 'تحليلات توازن اللعبة والاقتصاد')
@section('header_title')
  <i class="fa-solid fa-chart-line text-gold" style="color: var(--gold);"></i> تحليلات توازن الأدوار واقتصاد اللعبة (Game & Role Analytics)
@endsection

@section('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
<!-- Overview Metric Cards -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
  <div class="card" style="border-top: 3px solid #3b82f6;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">إجمالي المباريات الملعوبة</div>
    <div style="font-size: 32px; font-weight: 900; color: #fff;">{{ number_format($totalMatches) }}</div>
    <div style="font-size: 11px; color: #34d399; margin-top: 4px;"><i class="fa-solid fa-database"></i> مسجلة وموثقة بالكامل</div>
  </div>

  <div class="card" style="border-top: 3px solid #10b981;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">متوسط مدة المباراة</div>
    <div style="font-size: 32px; font-weight: 900; color: #34d399;">
      {{ floor($avgDuration / 60) }} د و {{ $avgDuration % 60 }} ث
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">من بداية اللوبي حتى حسم النصر</div>
  </div>

  <div class="card" style="border-top: 3px solid #f59e0b;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">إجمالي العملات المتداولة (Dcoins)</div>
    <div style="font-size: 32px; font-weight: 900; color: var(--gold);">
      {{ number_format($economyMetrics['total_coins'] ?? 0) }}
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">متوسط اللاعب: {{ number_format($economyMetrics['avg_coins'] ?? 0) }} عملة</div>
  </div>

  <div class="card" style="border-top: 3px solid #8b5cf6;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">حجم توليد العملات (Faucets)</div>
    <div style="font-size: 32px; font-weight: 900; color: #a78bfa;">
      +{{ number_format($economyMetrics['estimated_faucet_coins'] ?? 0) }}
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">مكافآت الانتصارات والمشاركات</div>
  </div>
</div>

<!-- Faction Win Rates Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 28px;">
  <!-- Kingdom Card -->
  <div class="card" style="border-right: 4px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 style="font-size: 15px; font-weight: 800; color: #60a5fa; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-crown"></i> فريق المملكة (Kingdom)
      </h3>
      <span class="badge badge-blue">{{ $kingdomPercent }}%</span>
    </div>
    <div style="font-size: 24px; font-weight: 900; color: #fff; margin-bottom: 8px;">{{ number_format($kingdomWins) }} <span style="font-size: 12px; color: var(--text-muted);">انتصار</span></div>
    <div style="height: 6px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden;">
      <div style="width: {{ $kingdomPercent }}%; height: 100%; background: #3b82f6;"></div>
    </div>
  </div>

  <!-- Shadow Card -->
  <div class="card" style="border-right: 4px solid #ef4444;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 style="font-size: 15px; font-weight: 800; color: #f87171; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-skull"></i> فريق الظلال (Shadows)
      </h3>
      <span class="badge badge-red">{{ $shadowPercent }}%</span>
    </div>
    <div style="font-size: 24px; font-weight: 900; color: #fff; margin-bottom: 8px;">{{ number_format($shadowWins) }} <span style="font-size: 12px; color: var(--text-muted);">انتصار</span></div>
    <div style="height: 6px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden;">
      <div style="width: {{ $shadowPercent }}%; height: 100%; background: #ef4444;"></div>
    </div>
  </div>

  <!-- Neutral Card -->
  <div class="card" style="border-right: 4px solid #8b5cf6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 style="font-size: 15px; font-weight: 800; color: #a78bfa; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-masks-theater"></i> المحايد (Trickster / Solo)
      </h3>
      <span class="badge badge-purple">{{ $neutralPercent }}%</span>
    </div>
    <div style="font-size: 24px; font-weight: 900; color: #fff; margin-bottom: 8px;">{{ number_format($neutralWins) }} <span style="font-size: 12px; color: var(--text-muted);">انتصار</span></div>
    <div style="height: 6px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden;">
      <div style="width: {{ $neutralPercent }}%; height: 100%; background: #8b5cf6;"></div>
    </div>
  </div>
</div>

<!-- Section 1: Role Win-Rate Matrix & Chart -->
<div class="card" style="margin-bottom: 28px;">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fa-solid fa-scale-balanced"></i> مصفوفة توازن الأدوار الـ 22 (Role Win-Rate Matrix)
    </h3>
    <span class="badge badge-gold">مؤشر التوازن: 35% - 65% متوازن</span>
  </div>

  <!-- Chart Canvas -->
  <div style="margin-bottom: 24px; height: 280px; position: relative;">
    <canvas id="roleWinRateChart"></canvas>
  </div>

  <!-- Table Matrix -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>الدور</th>
          <th>الفصيل</th>
          <th>عدد الظهور</th>
          <th>مرات الفوز</th>
          <th>نسبة الفوز (Win Rate)</th>
          <th>معدل البقاء حياً (Survival)</th>
          <th>حالة التوازن</th>
        </tr>
      </thead>
      <tbody>
        @foreach($roleMatrix as $roleKey => $item)
          @php
            $fBadge = $item['faction'] === 'kingdom' ? 'badge-blue' : ($item['faction'] === 'shadow' ? 'badge-red' : 'badge-purple');
            $fName = $item['faction'] === 'kingdom' ? 'مملكة' : ($item['faction'] === 'shadow' ? 'ظلال' : 'محايد');
          @endphp
          <tr>
            <td>
              <strong style="color: #fff; font-size: 14px;">{{ $roleKey }}</strong>
            </td>
            <td><span class="badge {{ $fBadge }}">{{ $fName }}</span></td>
            <td>{{ $item['appearances'] }}</td>
            <td>{{ $item['wins'] }}</td>
            <td>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-weight: 800; min-width: 44px;">{{ $item['win_rate'] }}%</span>
                <div style="width: 100px; height: 6px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden;">
                  <div style="width: {{ $item['win_rate'] }}%; height: 100%; background: {{ $item['win_rate'] > 65 ? '#ef4444' : ($item['win_rate'] < 35 ? '#f59e0b' : '#10b981') }};"></div>
                </div>
              </div>
            </td>
            <td>{{ $item['survival_rate'] }}%</td>
            <td>
              @if($item['status'] === 'OVERPOWERED')
                <span class="badge badge-red" title="نسبة فوز مرتفعة جداً">مبالغ بقوته 🔥</span>
              @elseif($item['status'] === 'UNDERPOWERED')
                <span class="badge badge-gold" title="نسبة فوز منخفضة">ضعيف ⚠️</span>
              @elseif($item['status'] === 'BALANCED')
                <span class="badge badge-green">متوازن ✅</span>
              @else
                <span class="badge badge-outline">بيانات غير كافية</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<!-- Section 2: Economy Leaderboard & Matches Log -->
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 28px;">
  <!-- Wealthiest Players -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-trophy"></i> أثرياء اللعبة (Top Balances)</h3>
    </div>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      @forelse($economyMetrics['top_wealthy'] ?? [] as $idx => $player)
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: rgba(0,0,0,0.25); border-radius: 8px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-weight: 900; color: {{ $idx === 0 ? 'var(--gold)' : 'var(--text-muted)' }}; font-size: 14px;">#{{ $idx + 1 }}</span>
            <div>
              <div style="font-weight: 800; font-size: 13px; color: #fff;">{{ $player->nickname }}</div>
              <div style="font-size: 11px; color: var(--text-muted);">{{ $player->stats_wins }} انتصار</div>
            </div>
          </div>
          <div style="font-weight: 900; color: var(--gold); font-size: 14px;">
            <i class="fa-solid fa-coins"></i> {{ number_format($player->coins) }}
          </div>
        </div>
      @empty
        <div style="text-align: center; color: var(--text-muted); padding: 20px;">لا يوجد لاعبين مسجلين بعد.</div>
      @endforelse
    </div>
  </div>

  <!-- Match History Log Table -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-list-check"></i> سجل المباريات المكتملة حديثاً</h3>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>معرف</th>
            <th>كود الغرفة</th>
            <th>الفريق الفائز</th>
            <th>مدة المباراة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          @forelse($matches as $m)
          <tr>
            <td><code>#{{ $m->match_id }}</code></td>
            <td><span class="badge badge-gold">{{ $m->room_code }}</span></td>
            <td>
              @php
                $wf = strtoupper($m->winning_faction);
                $bColor = str_contains($wf, 'KINGDOM') ? 'badge-blue' : (str_contains($wf, 'SHADOW') ? 'badge-red' : 'badge-purple');
              @endphp
              <span class="badge {{ $bColor }}">{{ $m->winning_faction }}</span>
            </td>
            <td>{{ floor($m->duration_seconds / 60) }}د {{ $m->duration_seconds % 60 }}ث</td>
            <td style="color: var(--text-muted); font-size: 11px;">{{ $m->created_at->format('Y-m-d H:i') }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="5" style="text-align: center; padding: 24px; color: var(--text-muted);">
              لم تسجل مباريات بعد.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($matches->hasPages())
      <div style="margin-top: 16px;">
        {{ $matches->links() }}
      </div>
    @endif
  </div>
</div>

@section('scripts')
<script>
  // Render Role Win Rate Chart
  const roleData = @json(array_slice($roleMatrix, 0, 10));
  const labels = Object.keys(roleData);
  const winRates = labels.map(k => roleData[k].win_rate);
  const backgroundColors = labels.map(k => {
    const f = roleData[k].faction;
    return f === 'kingdom' ? 'rgba(59, 130, 246, 0.7)' : (f === 'shadow' ? 'rgba(239, 68, 68, 0.7)' : 'rgba(139, 92, 246, 0.7)');
  });

  const ctx = document.getElementById('roleWinRateChart');
  if (ctx && labels.length > 0) {
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'نسبة الفوز (%)',
          data: winRates,
          backgroundColor: backgroundColors,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            min: 0,
            max: 100,
            ticks: { color: '#94a3b8' },
            grid: { color: 'rgba(255,255,255,0.06)' }
          },
          x: {
            ticks: { color: '#94a3b8' },
            grid: { display: false }
          }
        }
      }
    });
  }
</script>
@endsection
@endsection
