@extends('admin.layout')

@section('title', 'الضبط المباشر لقواعد اللعبة')
@section('header_title')
  <i class="fa-solid fa-sliders text-gold" style="color: var(--gold);"></i> الضبط المباشر لقواعد اللعبة (Live Hot-Tuning)
@endsection

@section('content')
<form action="{{ route('admin.tuning.update') }}" method="POST">
  @csrf

  <!-- Section 1: Timers -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <h3 class="card-title">
        <i class="fa-solid fa-stopwatch"></i> مؤقتات الجولات والتحكم بالزمن (Match Timers)
      </h3>
      <span class="badge badge-gold">تُطبق فورياً على أي غرفة جديدة</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-sun" style="color: #fbbf24;"></i> مدة مؤقت النهار (ثانية):
        </label>
        <input type="number" name="dayTimerSeconds" class="form-control" min="15" max="600"
               value="{{ old('dayTimerSeconds', $configs['day_timer_seconds'] ?? 120) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">الافتراضي: 120 ثانية (مرحلة النقاش وتبادل الشكوك)</small>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-moon" style="color: #60a5fa;"></i> مدة مؤقت الليل (ثانية):
        </label>
        <input type="number" name="nightTimerSeconds" class="form-control" min="15" max="300"
               value="{{ old('nightTimerSeconds', $configs['night_timer_seconds'] ?? 60) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">الافتراضي: 60 ثانية (تنفيذ قدرات الأدوار النشطة)</small>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-check-to-slot" style="color: #34d399;"></i> مدة مؤقت التصويت النهاري (ثانية):
        </label>
        <input type="number" name="votingTimerSeconds" class="form-control" min="15" max="300"
               value="{{ old('votingTimerSeconds', $configs['voting_timer_seconds'] ?? 60) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">الافتراضي: 60 ثانية (تصويت إعدام المشتبه به)</small>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-eye" style="color: #a78bfa;"></i> مدة شاشة كشف الأدوار (ثانية):
        </label>
        <input type="number" name="roleRevealTimerSeconds" class="form-control" min="3" max="30"
               value="{{ old('roleRevealTimerSeconds', $configs['role_reveal_timer_seconds'] ?? 8) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">الافتراضي: 8 ثوانٍ (شاشة تعريف اللاعب بدوره السري)</small>
      </div>
    </div>
  </div>

  <!-- Section 2: Economy & Win Conditions -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <h3 class="card-title">
        <i class="fa-solid fa-coins"></i> اقتصاد الجولات وقواعد الحسم (Economy & Game Rules)
      </h3>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-trophy" style="color: var(--gold);"></i> مكافأة عملات الفوز (Dcoins):
        </label>
        <input type="number" name="winCoinReward" class="form-control" min="0" max="1000"
               value="{{ old('winCoinReward', $configs['win_coin_reward'] ?? 50) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">تمنح لكل لاعب فائز بنهاية الجيم</small>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-award" style="color: var(--text-muted);"></i> مكافأة عملات الخسارة (Dcoins):
        </label>
        <input type="number" name="lossCoinReward" class="form-control" min="0" max="500"
               value="{{ old('lossCoinReward', $configs['loss_coin_reward'] ?? 10) }}" required>
        <small style="color: var(--text-muted); font-size: 11px;">تمنح للاعبين الخاسرين كمكافأة مشاركة</small>
      </div>

      <div class="form-group">
        <label class="form-label">
          <i class="fa-solid fa-scale-balanced" style="color: #60a5fa;"></i> قاعدة كسر التعادل في التصويت:
        </label>
        <select name="tieRule" class="form-control">
          <option value="NO_ELIMINATION" {{ ($configs['tie_rule'] ?? '') === 'NO_ELIMINATION' ? 'selected' : '' }}>
            عدم إعدام أي لاعب (No Elimination)
          </option>
          <option value="RANDOM_ELIMINATION" {{ ($configs['tie_rule'] ?? '') === 'RANDOM_ELIMINATION' ? 'selected' : '' }}>
            إعدام عشوائي بين المتعادلين (Random Elimination)
          </option>
        </select>
      </div>

      <div class="form-group" style="display: flex; flex-direction: column; justify-content: center;">
        <label class="form-label" style="margin-bottom: 10px;">
          <i class="fa-solid fa-crown" style="color: var(--gold);"></i> شرط بقاء الملك للمملكة:
        </label>
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
          <input type="checkbox" name="kingMustSurvive" value="1"
                 {{ !empty($configs['king_must_survive']) ? 'checked' : '' }}
                 style="width: 18px; height: 18px; accent-color: var(--gold);">
          <span>يجب بقاء الملك حياً لتحقيق نصر المملكة</span>
        </label>
      </div>
    </div>
  </div>

  <!-- Section 3: Role Switcher (Disable/Enable Roles) -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <h3 class="card-title">
        <i class="fa-solid fa-masks-theater"></i> إدارة تفعيل الأدوار (Role Switcher)
      </h3>
      <span class="badge badge-blue">حدد الأدوار التي ترغب في تعطيلها مؤقتاً عند حدوث خلل توازني</span>
    </div>

    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">
      الدور المحدد سيتم استبعاده فورياً من توزيع الأدوار في أي جولة جديدة واستبداله بدور متوازن تلقائياً من نفس الفصيل (مواطن للمملكة / قاتل للظلال).
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px;">
      @foreach($allRoles as $roleKey => $faction)
        @php
          $isDisabled = in_array($roleKey, $disabledRoles, true);
          $factionColor = $faction === 'kingdom' ? 'var(--blue)' : ($faction === 'shadow' ? 'var(--red)' : 'var(--purple)');
          $factionBadge = $faction === 'kingdom' ? 'badge-blue' : ($faction === 'shadow' ? 'badge-red' : 'badge-purple');
          $factionLabel = $faction === 'kingdom' ? 'مملكة' : ($faction === 'shadow' ? 'ظلال' : 'محايد');
        @endphp
        <div style="background: rgba(255,255,255,0.03); border: 1px solid {{ $isDisabled ? 'rgba(239, 68, 68, 0.4)' : 'rgba(255,255,255,0.08)' }}; border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 8px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <strong style="font-size: 13px; color: #fff;">{{ $roleKey }}</strong>
            <span class="badge {{ $factionBadge }}">{{ $factionLabel }}</span>
          </div>

          @if($roleKey === 'king')
            <small style="color: var(--gold); font-size: 11px;">
              <i class="fa-solid fa-lock"></i> دور أساسي (لا يمكن تعطيله)
            </small>
          @else
            <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; cursor: pointer; color: {{ $isDisabled ? '#f87171' : 'var(--text-muted)' }};">
              <input type="checkbox" name="disabledRoles[]" value="{{ $roleKey }}"
                     {{ $isDisabled ? 'checked' : '' }}
                     style="accent-color: var(--red);">
              <span>{{ $isDisabled ? 'معطل حالياً' : 'متاح باللعبة' }}</span>
            </label>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  <!-- Save Submit -->
  <div style="display: flex; justify-content: flex-end; gap: 14px; margin-bottom: 40px;">
    <button type="submit" class="btn btn-gold" style="font-size: 15px; padding: 12px 28px;">
      <i class="fa-solid fa-floppy-disk"></i> حفظ وتطبيق الإعدادات الحية الآن
    </button>
  </div>
</form>
@endsection

