@extends('admin.layout')

@section('title', 'منظومة مكافحة الغش والرقابة')
@section('header_title')
  <i class="fa-solid fa-user-secret text-gold" style="color: var(--gold);"></i> منظومة مكافحة التحالف والغش (Anti-Teaming & Moderation)
@endsection

@section('content')
<!-- Stats Grid -->
<div class="stat-grid">
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> بلاغات اشتباه معلقة</span>
      <span class="badge badge-red"><i class="fa-solid fa-bell"></i> يحتاج تدقيق</span>
    </div>
    <div style="font-size: 32px; font-weight: 900; color: #ef4444;">{{ $pendingAlertsCount }}</div>
    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">حالات تطابق IP أو كلمات مسيئة</div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-ghost"></i> المحظورين خفياً (Shadow-Banned)</span>
      <span class="badge badge-purple"><i class="fa-solid fa-user-slash"></i> عزل تام</span>
    </div>
    <div style="font-size: 32px; font-weight: 900; color: #a78bfa;">{{ $shadowBannedCount }}</div>
    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">لا ينضمون إلا مع بوتات أو محظورين</div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fa-solid fa-volume-xmark"></i> اللاعبين المكتومين (Muted)</span>
      <span class="badge badge-gold"><i class="fa-solid fa-clock"></i> مؤقت</span>
    </div>
    <div style="font-size: 32px; font-weight: 900; color: var(--gold);">{{ $mutedCount }}</div>
    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">محظورون مؤقتاً من شات اللعبة</div>
  </div>
</div>

<!-- Main Table Card -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fa-solid fa-shield-halved"></i> سجل الرادار الذكي والاشتباهات المكتشفة
    </h3>
    <div style="display: flex; gap: 8px;">
      <a href="{{ route('admin.moderation', ['status' => 'all']) }}" class="btn {{ $statusFilter === 'all' ? 'btn-gold' : 'btn-outline' }}" style="padding: 6px 12px; font-size: 12px;">الكل</a>
      <a href="{{ route('admin.moderation', ['status' => 'pending']) }}" class="btn {{ $statusFilter === 'pending' ? 'btn-gold' : 'btn-outline' }}" style="padding: 6px 12px; font-size: 12px;">معلقة فقط</a>
      <a href="{{ route('admin.moderation', ['status' => 'dismissed']) }}" class="btn {{ $statusFilter === 'dismissed' ? 'btn-gold' : 'btn-outline' }}" style="padding: 6px 12px; font-size: 12px;">تم تجاهلها</a>
    </div>
  </div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>الغرفة</th>
          <th>أطراف الاشتباه</th>
          <th>نوع المخالفة والسبب</th>
          <th>عنوان الـ IP</th>
          <th>الخطورة</th>
          <th>الحالة</th>
          <th>التاريخ والوقت</th>
          <th>الإجراءات</th>
        </tr>
      </thead>
      <tbody>
        @forelse($activities as $act)
          <tr>
            <td>#{{ $act->id }}</td>
            <td>
              <span class="badge badge-gold" style="letter-spacing: 1px;">{{ $act->room_code }}</span>
            </td>
            <td>
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <div style="display: flex; align-items: center; gap: 6px;">
                  <strong style="color: #60a5fa;">{{ $act->playerA->nickname ?? ($act->details['playerAName'] ?? $act->player_a_id) }}</strong>
                  @if(!empty($act->details['playerARole']))
                    <span class="badge badge-blue" style="font-size: 10px;">{{ $act->details['playerARole'] }}</span>
                  @endif
                </div>

                @if($act->player_b_id)
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 10px; color: var(--text-muted);">&bull; ضد:</span>
                    <strong style="color: #f87171;">{{ $act->playerB->nickname ?? ($act->details['playerBName'] ?? $act->player_b_id) }}</strong>
                    @if(!empty($act->details['playerBRole']))
                      <span class="badge badge-red" style="font-size: 10px;">{{ $act->details['playerBRole'] }}</span>
                    @endif
                  </div>
                @endif
              </div>
            </td>
            <td>
              @if($act->reason === 'SAME_IP_OPPOSING_TEAMS')
                <span class="badge badge-red"><i class="fa-solid fa-network-wired"></i> تحالف IP بين فصيلين متخاصمين</span>
              @elseif($act->reason === 'SAME_IP_MULTI_ACCOUNT')
                <span class="badge badge-gold"><i class="fa-solid fa-users-viewfinder"></i> تعدد حسابات بنفس الـ IP</span>
              @elseif($act->reason === 'CHAT_TOXICITY')
                <span class="badge badge-red"><i class="fa-solid fa-comment-slash"></i> ألفاظ مسيئة بالشات</span>
                @if(!empty($act->details['text']))
                  <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    "{{ $act->details['text'] }}"
                  </div>
                @endif
              @elseif($act->reason === 'ROLE_LEAK')
                <span class="badge badge-purple"><i class="fa-solid fa-bullhorn"></i> تسريب أدوار بالشات</span>
              @else
                <span class="badge badge-outline">{{ $act->reason }}</span>
              @endif
            </td>
            <td>
              <code style="font-size: 12px; color: #93c5fd;">{{ $act->ip_address ?? '—' }}</code>
            </td>
            <td>
              @if($act->severity === 'critical')
                <span class="badge badge-red"><i class="fa-solid fa-skull"></i> حرجة جداً</span>
              @else
                <span class="badge badge-gold"><i class="fa-solid fa-triangle-exclamation"></i> تحذير</span>
              @endif
            </td>
            <td>
              @if($act->status === 'pending')
                <span class="badge badge-red">معلق</span>
              @elseif($act->status === 'actioned')
                <span class="badge badge-green">تم اتخاذ إجراء</span>
              @else
                <span class="badge badge-outline">متجاهل</span>
              @endif
            </td>
            <td style="font-size: 11px; color: var(--text-muted);">
              {{ $act->created_at->format('Y-m-d H:i') }}
            </td>
            <td>
              <div style="display: flex; gap: 6px; align-items: center;">
                @if($act->playerA)
                  <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;"
                          onclick="openMuteModal('{{ $act->playerA->player_id }}', '{{ $act->playerA->nickname }}')">
                    <i class="fa-solid fa-volume-xmark"></i> كتم
                  </button>

                  <form action="{{ route('admin.moderation.shadowBan', $act->playerA->player_id) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn {{ $act->playerA->is_shadow_banned ? 'btn-gold' : 'btn-purple' }}"
                            style="padding: 4px 8px; font-size: 11px;"
                            title="{{ $act->playerA->is_shadow_banned ? 'إلغاء الحظر الخفي' : 'عزل بحظر خفي' }}">
                      <i class="fa-solid fa-ghost"></i> {{ $act->playerA->is_shadow_banned ? 'فك الخفي' : 'خفي' }}
                    </button>
                  </form>
                @endif

                @if($act->status === 'pending')
                  <form action="{{ route('admin.moderation.dismiss', $act->id) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" title="تجاهل البلاغ">
                      <i class="fa-solid fa-check"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" style="text-align: center; padding: 36px; color: var(--text-muted);">
              <i class="fa-solid fa-shield-heart" style="font-size: 32px; color: #10b981; margin-bottom: 12px; display: block;"></i>
              لا توجد بلاغات اشتباه أو تحالف مسجلة حالياً! البيئة نظيفة وآمنة.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($activities->hasPages())
    <div style="padding-top: 16px;">
      {{ $activities->links() }}
    </div>
  @endif
</div>

<!-- Quick Mute Modal -->
<div class="modal" id="muteModal">
  <div class="modal-box">
    <div class="card-header" style="margin-bottom: 14px;">
      <h3 class="card-title"><i class="fa-solid fa-volume-xmark"></i> كتم اللاعب من المحادثة</h3>
      <button type="button" onclick="closeMuteModal()" style="background: none; border: none; color: #999; font-size: 18px; cursor: pointer;">&times;</button>
    </div>
    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;" id="muteModalSubtitle">
      اختر مدة الكتم المناسبة للاعب:
    </p>

    <form id="muteModalForm" method="POST">
      @csrf
      <div class="form-group">
        <label class="form-label">المدة الزمنية للكتم:</label>
        <select name="duration" class="form-control">
          <option value="60">ساعة واحدة (1h)</option>
          <option value="360">6 ساعات (6h)</option>
          <option value="1440" selected>24 ساعة (يوم كامل)</option>
          <option value="10080">7 أيام (أسبوع)</option>
          <option value="0">رفع الكتم فوراً (Unmute)</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-outline" onclick="closeMuteModal()">إلغاء</button>
        <button type="submit" class="btn btn-red"><i class="fa-solid fa-gavel"></i> تطبيق الكتم الآن</button>
      </div>
    </form>
  </div>
</div>

@section('scripts')
<script>
  function openMuteModal(playerId, nickname) {
    const modal = document.getElementById('muteModal');
    const form = document.getElementById('muteModalForm');
    const subtitle = document.getElementById('muteModalSubtitle');

    form.action = `/admin/moderation/players/${playerId}/mute`;
    subtitle.textContent = `سيتم كتم اللاعب "${nickname}" ومنعه من إرسال الرسائل في جميع الغرف الحية:`;
    modal.classList.add('active');
  }

  function closeMuteModal() {
    document.getElementById('muteModal').classList.remove('active');
  }
</script>
@endsection
@endsection
