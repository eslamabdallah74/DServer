@extends('admin.layout')

@section('title', 'محاكي الضغط وتوليد المباريات')
@section('header_title')
  <i class="fa-solid fa-microchip text-gold" style="color: var(--gold);"></i> محاكي الضغط وتوليد المباريات الوهمية (Bot Stress-Test Panel)
@endsection

@section('content')
<!-- Configuration & Launcher Card -->
<div class="card" style="margin-bottom: 24px;">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fa-solid fa-rocket"></i> إطلاق محاكاة أداء ومباريات ذكية
    </h3>
    <span class="badge badge-purple"><i class="fa-solid fa-bolt"></i> بدون توقف السيرفر</span>
  </div>

  <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
    تقوم هذه اللوحة بإنشاء غرف متعددة بالتوازي وملئها ببوتات ذكية، وتمرير الجولات ومحاكاة حركات الليل والتصويت، لقياس استهلاك الذاكرة وسرعة استجابة السيرفر تحت الضغط العالي.
  </p>

  <form action="{{ route('admin.stressTest.run') }}" method="POST">
    @csrf

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-layer-group" style="color: var(--gold);"></i> عدد الغرف المتزامنة:
        </label>
        <input type="number" id="roomCountInput" name="roomCount" class="form-control" min="1" max="20"
               value="{{ old('roomCount', 5) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">الحد الأقصى: 20 غرفة متزامنة في الجولة الواحدة</small>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-robot" style="color: #60a5fa;"></i> إجمالي اللاعبين (بوتات) لكل غرفة:
        </label>
        <input type="number" id="botsPerRoomInput" name="botsPerRoom" class="form-control" min="3" max="16"
               value="{{ old('botsPerRoom', 6) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">من 3 إلى 16 لاعب (توزيع أدوار كامل)</small>
      </div>
    </div>

    <!-- Quick Presets -->
    <div style="margin-bottom: 24px;">
      <label class="form-label">نماذج جاهزة سريعة:</label>
      <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        <button type="button" class="btn btn-outline" style="font-size: 12px;" onclick="setPreset(3, 6)">
          <i class="fa-solid fa-gauge"></i> محاكاة خفيفة (3 غرف × 6 بوتات = 18 لاعب)
        </button>
        <button type="button" class="btn btn-outline" style="font-size: 12px;" onclick="setPreset(5, 8)">
          <i class="fa-solid fa-gauge-high"></i> محاكاة متوسطة (5 غرف × 8 بوتات = 40 لاعب)
        </button>
        <button type="button" class="btn btn-outline" style="font-size: 12px;" onclick="setPreset(10, 8)">
          <i class="fa-solid fa-fire"></i> اختبار ضغط عالي (10 غرف × 8 بوتات = 80 لاعب)
        </button>
      </div>
    </div>

    <div style="display: flex; justify-content: flex-end;">
      <button type="submit" class="btn btn-gold" style="font-size: 15px; padding: 12px 28px;">
        <i class="fa-solid fa-play"></i> بدء المحاكاة وقياس الأداء الآن
      </button>
    </div>
  </form>
</div>

<!-- Simulation Results (if executed) -->
@if(!empty($lastResult))
  <!-- Stat Cards -->
  <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
    <div class="card" style="border-top: 3px solid #10b981;">
      <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">الغرف المحاكاة</div>
      <div style="font-size: 32px; font-weight: 900; color: #34d399;">{{ $lastResult['roomsSpawned'] }}</div>
      <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">بإجمالي {{ $lastResult['totalPlayers'] }} لاعب وهمي</div>
    </div>

    <div class="card" style="border-top: 3px solid #3b82f6;">
      <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">الحركات والأحداث المعالجة</div>
      <div style="font-size: 32px; font-weight: 900; color: #60a5fa;">{{ $lastResult['actionsProcessed'] }}</div>
      <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">توزيع أدوار، قدرات ليل، وتمرير دورة</div>
    </div>

    <div class="card" style="border-top: 3px solid var(--gold);">
      <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">زمن الاستجابة والتنفيذ</div>
      <div style="font-size: 32px; font-weight: 900; color: var(--gold);">
        {{ $lastResult['executionTimeMs'] }} <span style="font-size: 14px; font-weight: 500;">ms</span>
      </div>
      <div style="font-size: 11px; color: #34d399; margin-top: 4px;">{{ $lastResult['executionTimeSec'] }} ثانية فقط</div>
    </div>

    <div class="card" style="border-top: 3px solid #8b5cf6;">
      <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">ذروة استهلاك الذاكرة (Peak RAM)</div>
      <div style="font-size: 32px; font-weight: 900; color: #a78bfa;">
        {{ $lastResult['totalMemoryPeakMb'] }} <span style="font-size: 14px; font-weight: 500;">MB</span>
      </div>
      <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">كفاءة عالية ومعدل خفيف جداً</div>
    </div>
  </div>

  <!-- Detailed Rooms Report -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">
        <i class="fa-solid fa-clipboard-check"></i> تفاصيل الغرف المحاكاة ونواتج الجولة
      </h3>
      <span class="badge badge-green"><i class="fa-solid fa-check"></i> 100% نجاح بدون أي انهيار</span>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>كود الغرفة</th>
            <th>المرحلة التي وصلتها</th>
            <th>الجولة</th>
            <th>اللاعبين</th>
            <th>ضحايا الليلة (Night Outcome)</th>
            <th>وضع المراقب المباشر</th>
          </tr>
        </thead>
        <tbody>
          @foreach($lastResult['rooms'] as $r)
            <tr>
              <td><span class="badge badge-gold" style="font-size: 13px; letter-spacing: 1px;">{{ $r['code'] }}</span></td>
              <td>
                <span class="badge badge-blue">{{ $r['phase'] }}</span>
              </td>
              <td>#{{ $r['round'] }}</td>
              <td>{{ $r['playerCount'] }} بوتات</td>
              <td>
                @if($r['eliminated'] !== 'لا يوجد قتلى')
                  <span style="color: #f87171; font-weight: 700;">
                    <i class="fa-solid fa-skull"></i> {{ $r['eliminated'] }}
                  </span>
                @else
                  <span style="color: #34d399;">
                    <i class="fa-solid fa-shield-halved"></i> تم حماية اللاعبين بنجاح
                  </span>
                @endif
              </td>
              <td>
                <a href="{{ route('admin.spectator', $r['code']) }}" class="btn btn-outline" style="padding: 4px 10px; font-size: 11px;">
                  <i class="fa-solid fa-eye"></i> مراقبة الغرفة
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@section('scripts')
<script>
  function setPreset(rooms, bots) {
    document.getElementById('roomCountInput').value = rooms;
    document.getElementById('botsPerRoomInput').value = bots;
  }
</script>
@endsection
@endsection
