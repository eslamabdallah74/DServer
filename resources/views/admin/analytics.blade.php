@extends('admin.layout')

@section('title', 'تحليلات اللعبة الشاملة')
@section('header_title', 'تحليلات أداء ومباريات اللعبة (Game Analytics)')

@section('content')
<!-- Overview Metric Cards -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
  <div class="card" style="border-top: 3px solid #3b82f6;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">إجمالي المباريات الملعوبة</div>
    <div style="font-size: 34px; font-weight: 900; color: #fff;">{{ number_format($totalMatches) }}</div>
    <div style="font-size: 11px; color: #34d399; margin-top: 4px;"><i class="fa-solid fa-arrow-trend-up"></i> بيانات متصلة ومسجلة تلقائياً</div>
  </div>

  <div class="card" style="border-top: 3px solid #10b981;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">متوسط مدة المباراة</div>
    <div style="font-size: 34px; font-weight: 900; color: #34d399;">
      {{ floor($avgDuration / 60) }} د و {{ $avgDuration % 60 }} ث
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">متوسط وقت الجولات من البداية للنهاية</div>
  </div>

  <div class="card" style="border-top: 3px solid #f59e0b;">
    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">نسبة حسم المباريات</div>
    <div style="font-size: 34px; font-weight: 900; color: var(--gold);">
      {{ $totalMatches > 0 ? '100%' : '0%' }}
    </div>
    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">معدل اكتمال المباريات بدون توقف</div>
  </div>
</div>

<!-- Faction Win Rates Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px;">
  <!-- Kingdom Card -->
  <div class="card" style="border-right: 4px solid #3b82f6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <h3 style="font-size: 16px; font-weight: 800; color: #60a5fa; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-crown"></i> فريق المملكة (Kingdom)
      </h3>
      <span class="badge badge-blue" style="font-size: 14px;">{{ $kingdomPercent }}%</span>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 10px;">{{ number_format($kingdomWins) }} <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">انتصار مسجل</span></div>
    <div style="height: 8px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
      <div style="width: {{ $kingdomPercent }}%; height: 100%; background: #3b82f6;"></div>
    </div>
    <p style="font-size: 12px; color: var(--text-muted);">
      شروط النصر: القضاء التام على جميع الظلال، أو إنجاز 100٪ من مهام القرية المشتركة.
    </p>
  </div>

  <!-- Shadow Card -->
  <div class="card" style="border-right: 4px solid #ef4444;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <h3 style="font-size: 16px; font-weight: 800; color: #f87171; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-skull"></i> فريق الظلال (Shadows)
      </h3>
      <span class="badge badge-red" style="font-size: 14px;">{{ $shadowPercent }}%</span>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 10px;">{{ number_format($shadowWins) }} <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">انتصار مسجل</span></div>
    <div style="height: 8px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
      <div style="width: {{ $shadowPercent }}%; height: 100%; background: #ef4444;"></div>
    </div>
    <p style="font-size: 12px; color: var(--text-muted);">
      شروط النصر: سقوط الملك وخلو العرش، أو التفوق العددي على المملكة، أو التعادل وانعدام الحماية.
    </p>
  </div>

  <!-- Neutral Card -->
  <div class="card" style="border-right: 4px solid #8b5cf6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <h3 style="font-size: 16px; font-weight: 800; color: #a78bfa; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-masks-theater"></i> المحايد (المخادع / Solo)
      </h3>
      <span class="badge badge-purple" style="font-size: 14px;">{{ $neutralPercent }}%</span>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 10px;">{{ number_format($neutralWins) }} <span style="font-size: 13px; color: var(--text-muted); font-weight: 500;">انتصار منفرد</span></div>
    <div style="height: 8px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
      <div style="width: {{ $neutralPercent }}%; height: 100%; background: #8b5cf6;"></div>
    </div>
    <p style="font-size: 12px; color: var(--text-muted);">
      شروط النصر: نجاح المخادع (Trickster) في خداع اللاعبين وإقناعهم بالتصويت ضده نهاراً.
    </p>
  </div>
</div>

<!-- Match History Log Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-list-check"></i> سجل المباريات المكتملة والأداء التاريخي</h3>
  </div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>معرف المباراة</th>
          <th>كود الغرفة</th>
          <th>الفريق الفائز</th>
          <th>مدة المباراة</th>
          <th>عدد اللاعبين</th>
          <th>التاريخ والوقت</th>
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
          <td>
            {{ floor($m->duration_seconds / 60) }} د و {{ $m->duration_seconds % 60 }} ث
          </td>
          <td>
            @php
              $playersCount = is_array($m->players_json) ? count($m->players_json) : (is_string($m->players_json) ? count(json_decode($m->players_json, true) ?: []) : 0);
            @endphp
            {{ $playersCount }} لاعبين
          </td>
          <td style="color: var(--text-muted); font-size: 12px;">{{ $m->created_at->format('Y-m-d H:i') }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
            لم يتم تسجيل مباريات مكتملة بعد. عند انتهاء أول مباراة ستظهر بياناتها التحليلية هنا تلقائياً.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top: 20px;">
    {{ $matches->links() }}
  </div>
</div>
@endsection
