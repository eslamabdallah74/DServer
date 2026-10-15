@extends('admin.layout')

@section('title', 'الغرف الحية والتحكم بالمباريات')
@section('header_title', 'مركز إدارة الغرف الحية المباشرة')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 18px; font-weight: 800; color: #fff;">الغرف المتصلة الآن بالسيرفر ({{ count($activeRooms) }})</h2>
    <p style="font-size: 13px; color: var(--text-muted);">تحكم مباشر باللاعبين والمراحل والبوتات لجميع المباريات المقامة حالياً.</p>
  </div>
  <a href="{{ route('admin.rooms') }}" class="btn btn-outline"><i class="fa-solid fa-arrows-rotate"></i> تحديث الحالة</a>
</div>

@if(count($activeRooms) === 0)
  <div class="card" style="text-align: center; padding: 60px 20px;">
    <i class="fa-solid fa-ghost" style="font-size: 48px; color: var(--gold); opacity: 0.6; margin-bottom: 16px;"></i>
    <h3 style="font-size: 18px; margin-bottom: 8px;">لا توجد غرف نشطة حالياً</h3>
    <p style="color: var(--text-muted); font-size: 14px; max-width: 460px; margin: 0 auto;">
      عندما يفتح أي لاعب تطبيق الهاتف ويقوم بإنشاء غرفة، ستظهر تفاصيل الغرفة ولاعبيها ومرحلتها فورياً في هذه الشاشة.
    </p>
  </div>
@else
  <div style="display: flex; flex-direction: column; gap: 24px;">
    @foreach($activeRooms as $room)
      <div class="card" style="border: 1px solid var(--gold-light); background: #0f1522;">
        <!-- Room Header Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px; margin-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,0.06); flex-wrap: wrap; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 14px;">
            <span class="badge badge-gold" style="font-size: 16px; padding: 6px 14px; letter-spacing: 2px;">
              <i class="fa-solid fa-door-open"></i> {{ $room['roomCode'] }}
            </span>
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
            <span class="badge {{ $badgeClass }}" style="font-size: 13px;">{{ $phase }}</span>
            <span style="font-size: 13px; color: var(--text-muted);">جولة {{ $room['round'] ?? 0 }}</span>
            <span style="font-size: 13px; color: var(--text-muted);">
              المضيف: <strong>{{ $room['players'][0]['nickname'] ?? 'Host' }}</strong>
            </span>
          </div>

          <!-- Room Actions -->
          <div style="display: flex; gap: 8px;">
            <a href="{{ route('admin.spectator', $room['roomCode']) }}" class="btn btn-gold" style="font-size: 12px; padding: 6px 12px;">
              <i class="fa-solid fa-eye"></i> وضع المراقب
            </a>

            @if($phase === 'LOBBY')
              <form action="{{ route('admin.rooms.addBot', $room['roomCode']) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-blue" style="font-size: 12px; padding: 6px 12px;">
                  <i class="fa-solid fa-robot"></i> إضافة بوت
                </button>
              </form>
            @endif

            <form action="{{ route('admin.rooms.nextPhase', $room['roomCode']) }}" method="POST">
              @csrf
              <button type="submit" class="btn btn-outline" style="font-size: 12px; padding: 6px 12px;">
                <i class="fa-solid fa-forward-step"></i> تمرير المرحلة
              </button>
            </form>

            <form action="{{ route('admin.rooms.destroy', $room['roomCode']) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من إنهاء هذه الغرفة فورياً؟');">
              @csrf
              <button type="submit" class="btn btn-red" style="font-size: 12px; padding: 6px 12px;">
                <i class="fa-solid fa-trash"></i> إنهاء الغرفة
              </button>
            </form>
          </div>
        </div>

        <!-- Room Players List -->
        <div>
          <h4 style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-bottom: 12px;">
            اللاعبين المتواجدين ({{ count($room['players'] ?? []) }} لاعب):
          </h4>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
            @foreach($room['players'] ?? [] as $player)
              <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="font-weight: 800; font-size: 14px; color: #fff; display: flex; align-items: center; gap: 6px;">
                    @if($player['isBot'] ?? false)
                      <span class="badge badge-purple" style="font-size: 10px;"><i class="fa-solid fa-robot"></i> BOT</span>
                    @endif
                    @if($player['isHost'] ?? false)
                      <span class="badge badge-gold" style="font-size: 10px;"><i class="fa-solid fa-crown"></i> HOST</span>
                    @endif
                    {{ $player['nickname'] }}
                  </div>
                  <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: flex; gap: 8px;">
                    <span>الحالة: {{ ($player['isAlive'] ?? true) ? 'حي 🟢' : 'مستبعد 🔴' }}</span>
                    <span>جاهز: {{ ($player['isReady'] ?? false) ? '✅' : '⏳' }}</span>
                    @if(!empty($player['role']))
                      <span style="color: var(--gold);">الدور: {{ $player['role'] }}</span>
                    @endif
                  </div>
                </div>

                @if(!($player['isHost'] ?? false))
                  <form action="{{ route('admin.rooms.kickPlayer', ['roomCode' => $room['roomCode'], 'playerId' => $player['playerId']]) }}" method="POST" onsubmit="return confirm('طرد هذا اللاعب؟');">
                    @csrf
                    <button type="submit" class="btn btn-red" style="padding: 4px 8px; font-size: 11px;" title="طرد اللاعب">
                      <i class="fa-solid fa-user-xmark"></i>
                    </button>
                  </form>
                @endif
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection

