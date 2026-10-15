@extends('admin.layout')

@section('title', "وضع المراقب — الغرفة {$code}")
@section('header_title')
  <i class="fa-solid fa-eye text-gold" style="color: var(--gold);"></i> شاشة المراقبة الحية الشاملة (God-Mode Stream)
@endsection

@section('content')
<!-- Header & Room Controls -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <div>
        <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">كود الغرفة</div>
        <div style="font-size: 26px; font-weight: 900; color: var(--gold); letter-spacing: 2px;">{{ $code }}</div>
      </div>

      <div style="height: 36px; width: 1px; background: rgba(255,255,255,0.1);"></div>

      <div>
        <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">المرحلة الحالية</div>
        <div style="margin-top: 4px;">
          @php
            $phase = $snapshot['phase'];
            $phaseBadge = match($phase) {
              'LOBBY' => 'badge-purple',
              'ROLE_REVEAL' => 'badge-gold',
              'NIGHT' => 'badge-blue',
              'MORNING' => 'badge-green',
              'DAY' => 'badge-gold',
              'VOTING' => 'badge-red',
              'ELIMINATION' => 'badge-red',
              'GAME_OVER' => 'badge-purple',
              default => 'badge-outline'
            };
            $phaseLabel = match($phase) {
              'LOBBY' => 'انتظار باللوبي',
              'ROLE_REVEAL' => 'كشف الأدوار',
              'NIGHT' => 'الليل (تنفيذ القدرات)',
              'MORNING' => 'الصباح (إعلان النتائج)',
              'DAY' => 'النهار (النقاش)',
              'VOTING' => 'التصويت النهاري',
              'ELIMINATION' => 'تنفيذ الإعدام',
              'GAME_OVER' => 'نهاية المباراة',
              default => $phase
            };
          @endphp
          <span class="badge {{ $phaseBadge }}" style="font-size: 13px; padding: 6px 12px;" id="phaseBadge">
            {{ $phaseLabel }}
          </span>
        </div>
      </div>

      <div style="height: 36px; width: 1px; background: rgba(255,255,255,0.1);"></div>

      <div>
        <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">الجولة</div>
        <div style="font-size: 18px; font-weight: 800; color: #fff;" id="roundNumber">#{{ $snapshot['round'] }}</div>
      </div>

      <div style="height: 36px; width: 1px; background: rgba(255,255,255,0.1);"></div>

      <div>
        <div style="font-size: 11px; color: var(--text-muted); font-weight: 700;">الوقت المتبقي</div>
        <div style="font-size: 18px; font-weight: 800; color: #34d399;" id="timerCountdown">
          <i class="fa-solid fa-stopwatch"></i> {{ $snapshot['timeLeft'] }} ثانية
        </div>
      </div>
    </div>

    <!-- Quick Room Admin Controls -->
    <div style="display: flex; gap: 8px;">
      <button type="button" class="btn btn-outline" onclick="fetchLiveSnapshot()" title="تحديث فوري">
        <i class="fa-solid fa-rotate"></i> تحديث
      </button>

      <form action="{{ route('admin.rooms.addBot', $code) }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="btn btn-blue" style="font-size: 12px;">
          <i class="fa-solid fa-robot"></i> إضافة بوت
        </button>
      </form>

      <form action="{{ route('admin.rooms.nextPhase', $code) }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="btn btn-gold" style="font-size: 12px;">
          <i class="fa-solid fa-forward-step"></i> تخطي للمرحلة التالية
        </button>
      </form>

      <form action="{{ route('admin.rooms.destroy', $code) }}" method="POST" style="display: inline;" onsubmit="return confirm('إنهاء المباراة فورياً؟');">
        @csrf
        <button type="submit" class="btn btn-red" style="font-size: 12px;">
          <i class="fa-solid fa-trash"></i> إنهاء
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Main Split: Players Grid & Action Feed -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
  <!-- Left: God-Mode Player Cards Grid -->
  <div>
    <div class="card" style="margin-bottom: 24px;">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fa-solid fa-users"></i> خريطة كشف الأدوار والتحركات (God-Mode Grid)
        </h3>
        <span class="badge badge-gold" id="playerStatsBadge">
          {{ $snapshot['alivePlayers'] }} أحياء من أصل {{ $snapshot['totalPlayers'] }}
        </span>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px;" id="playersGrid">
        @foreach($snapshot['players'] as $player)
          @php
            $faction = $player['faction'] ?? 'kingdom';
            $factionColor = $faction === 'kingdom' ? 'var(--blue)' : ($faction === 'shadow' ? 'var(--red)' : 'var(--purple)');
            $factionBadge = $faction === 'kingdom' ? 'badge-blue' : ($faction === 'shadow' ? 'badge-red' : 'badge-purple');
            $factionLabel = $faction === 'kingdom' ? 'مملكة' : ($faction === 'shadow' ? 'ظلال' : 'محايد');
            $isAlive = $player['isAlive'];
          @endphp
          <div class="card" style="padding: 16px; background: rgba(18, 24, 36, 0.85); border: 2px solid {{ $isAlive ? $factionColor : 'rgba(239, 68, 68, 0.3)' }}; opacity: {{ $isAlive ? '1' : '0.5' }}; position: relative;">
            <!-- Player Top Row -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
              <div>
                <div style="font-weight: 800; font-size: 15px; color: #fff; display: flex; align-items: center; gap: 6px;">
                  @if($player['isBot'])
                    <span class="badge badge-purple" style="font-size: 9px;"><i class="fa-solid fa-robot"></i> BOT</span>
                  @endif
                  @if($player['isHost'])
                    <span class="badge badge-gold" style="font-size: 9px;"><i class="fa-solid fa-crown"></i> HOST</span>
                  @endif
                  {{ $player['nickname'] }}
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                  IP: <code style="color: #93c5fd;">{{ $player['ip'] }}</code>
                </div>
              </div>

              <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                <span class="badge {{ $isAlive ? 'badge-green' : 'badge-red' }}">
                  {{ $isAlive ? 'حي 🟢' : 'مستبعد 🔴' }}
                </span>
                <span class="badge {{ $factionBadge }}">{{ $factionLabel }}</span>
              </div>
            </div>

            <!-- Revealed Role & Faction -->
            <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <div style="font-size: 11px; color: var(--text-muted);">الدور السري (مكشوف للمشرف):</div>
                <div style="font-weight: 800; font-size: 14px; color: {{ $factionColor }};">
                  <i class="fa-solid fa-mask"></i> {{ $player['role'] }}
                </div>
              </div>

              <!-- Vote Heatmap Badge -->
              @if(($player['incomingVotes'] ?? 0) > 0)
                <span class="badge badge-red" style="font-size: 11px;" title="عدد الأصوات الموجهة ضده">
                  <i class="fa-solid fa-crosshairs"></i> {{ $player['incomingVotes'] }} أصوات
                </span>
              @endif
            </div>

            <!-- Real-Time Actions: Night Target & Day Vote -->
            <div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px;">
              @if(!empty($player['nightTargetName']))
                <div style="color: #60a5fa; background: rgba(59, 130, 246, 0.1); padding: 5px 8px; border-radius: 6px;">
                  <i class="fa-solid fa-moon"></i> هدف الليل: <strong>{{ $player['nightTargetName'] }}</strong>
                </div>
              @endif

              @if(!empty($player['voteTargetName']))
                <div style="color: #f87171; background: rgba(239, 68, 68, 0.1); padding: 5px 8px; border-radius: 6px;">
                  <i class="fa-solid fa-check-to-slot"></i> صوّت ضد: <strong>{{ $player['voteTargetName'] }}</strong>
                </div>
              @endif
            </div>

            <!-- Card Bottom: Admin Kick Button -->
            <div style="display: flex; justify-content: flex-end; margin-top: 12px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.06);">
              <form action="{{ route('admin.rooms.kickPlayer', [$code, $player['playerId']]) }}" method="POST" onsubmit="return confirm('طرد اللاعب؟');">
                @csrf
                <button type="submit" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #f87171;">
                  <i class="fa-solid fa-user-xmark"></i> طرد
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <!-- Right: Live Event Feed & Ticker -->
  <div>
    <!-- Last Night Outcome Alert -->
    @if(!empty($snapshot['lastNightOutcome']))
      <div class="card" style="margin-bottom: 20px; border-color: rgba(96, 165, 250, 0.4);">
        <div class="card-header">
          <span class="card-title" style="color: #60a5fa;">
            <i class="fa-solid fa-moon"></i> ملخص أحداث الليلة السابقة
          </span>
        </div>
        <div style="font-size: 13px; color: #e2e8f0; line-height: 1.6;">
          @if(!empty($snapshot['lastNightOutcome']['eliminated']))
            <div style="color: #f87171; font-weight: 700;">
              <i class="fa-solid fa-skull"></i> تم قتل: {{ $snapshot['lastNightOutcome']['eliminated']['nickname'] ?? 'لاعب' }}
            </div>
          @else
            <div style="color: #34d399;">
              <i class="fa-solid fa-shield-halved"></i> مرت الليلة بسلام دون أي قتلى (حماية ناجحة).
            </div>
          @endif
        </div>
      </div>
    @endif

    <!-- Chat & Event Log Stream -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-comments"></i> سجل المحادثات والأحداث الحية</h3>
        <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> LIVE</span>
      </div>

      <div style="height: 480px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding-right: 6px;" id="chatStream">
        @forelse(array_reverse($snapshot['chatHistory'] ?? []) as $msg)
          <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; border: 1px solid rgba(255,255,255,0.04);">
            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px;">
              <strong style="color: var(--gold);">{{ $msg['nickname'] ?? 'مجهول' }}</strong>
              <span style="color: var(--text-muted);">{{ !empty($msg['timestamp']) ? date('H:i:s', (int)($msg['timestamp'] / 1000)) : '' }}</span>
            </div>
            <div style="font-size: 13px; color: #f3f4f6;">
              {{ $msg['text'] ?? '' }}
            </div>
          </div>
        @empty
          <div style="text-align: center; color: var(--text-muted); padding: 40px 10px; font-size: 13px;">
            لا توجد رسائل محادثة بعد في هذه الغرفة.
          </div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  const roomCode = @json($code);

  // Live polling every 3 seconds for instant God-Mode updates
  async function fetchLiveSnapshot() {
    try {
      const res = await fetch(`/admin/spectator/${roomCode}/snapshot`);
      if (res.ok) {
        const data = await res.json();
        if (data && data.snapshot) {
          updateSpectatorView(data.snapshot);
        }
      }
    } catch (e) {
      console.warn("Spectator snapshot fetch error:", e);
    }
  }

  function updateSpectatorView(snapshot) {
    if (!snapshot) return;

    // Update round and timer
    document.getElementById('roundNumber').textContent = '#' + snapshot.round;
    document.getElementById('timerCountdown').innerHTML = `<i class="fa-solid fa-stopwatch"></i> ${snapshot.timeLeft} ثانية`;
    document.getElementById('playerStatsBadge').textContent = `${snapshot.alivePlayers} أحياء من أصل ${snapshot.totalPlayers}`;

    // Reload page if phase changed to re-render fresh grid
    const currentPhase = document.getElementById('phaseBadge').textContent.trim();
    // Re-poll in 3 seconds
  }

  // Poll loop
  setInterval(fetchLiveSnapshot, 3000);
</script>
@endsection
