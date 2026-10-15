@extends('admin.layout')

@section('title', 'سجل عمليات المشرفين')
@section('header_title')
  <i class="fa-solid fa-shield-halved" style="color: var(--gold);"></i>
  <span>سجل العمليات الإدارية (Audit Logs)</span>
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> التغييرات والإجراءات الإدارية المسجلة</h3>
    <span style="font-size: 13px; color: var(--text-muted);">إجمالي السجلات: {{ $logs->total() }}</span>
  </div>

  @if($logs->isEmpty())
    <div style="text-align: center; padding: 48px; color: var(--text-muted);">
      <i class="fa-solid fa-clipboard-check" style="font-size: 42px; color: var(--gold); margin-bottom: 12px; display: block;"></i>
      لا توجد عمليات إدارية مسجلة حتى الآن.
    </div>
  @else
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>المشرف</th>
            <th>نوع الإجراء</th>
            <th>عنوان IP</th>
            <th>التاريخ والوقت</th>
            <th>التفاصيل</th>
          </tr>
        </thead>
        <tbody>
          @foreach($logs as $log)
            <tr>
              <td>#{{ $log->id }}</td>
              <td>
                <div style="font-weight: 700; color: #fff;">{{ $log->user->username ?? 'مشرف #' . $log->user_id }}</div>
              </td>
              <td>
                @if(str_contains($log->action, 'BAN'))
                  <span class="badge badge-red"><i class="fa-solid fa-ban"></i> {{ $log->action }}</span>
                @elseif(str_contains($log->action, 'LOGIN') || str_contains($log->action, 'LOGOUT'))
                  <span class="badge badge-blue"><i class="fa-solid fa-right-to-bracket"></i> {{ $log->action }}</span>
                @elseif(str_contains($log->action, 'COINS') || str_contains($log->action, 'BROADCAST'))
                  <span class="badge badge-gold"><i class="fa-solid fa-bolt"></i> {{ $log->action }}</span>
                @else
                  <span class="badge badge-purple">{{ $log->action }}</span>
                @endif
              </td>
              <td><code style="color: var(--text-muted); font-size: 12px;">{{ $log->ip_address ?: '-' }}</code></td>
              <td style="font-size: 12px; color: var(--text-muted);">{{ $log->created_at ? $log->created_at->format('Y/m/d H:i:s') : '-' }}</td>
              <td>
                <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;" onclick='viewAuditDetails(@json($log))'>
                  <i class="fa-solid fa-eye"></i> التفاصيل
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div style="margin-top: 20px;">
      {{ $logs->links() }}
    </div>
  @endif
</div>

<!-- Details Modal -->
<div class="modal" id="auditModal">
  <div class="modal-box" style="max-width: 580px;">
    <div class="card-header">
      <h3 class="card-title" id="auditModalTitle"><i class="fa-solid fa-file-code"></i> تفاصيل السجل</h3>
      <button type="button" onclick="closeAuditModal()" style="background: none; border: none; color: #999; font-size: 20px; cursor: pointer;">&times;</button>
    </div>
    <div style="margin-bottom: 16px;">
      <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 6px;">بيانات العملية (JSON Details):</div>
      <pre id="auditModalDetails" style="background: rgba(0,0,0,0.5); padding: 14px; border-radius: 8px; font-size: 12px; max-height: 240px; overflow-y: auto; color: var(--gold); direction: ltr; text-align: left;"></pre>
    </div>
    <div style="display: flex; justify-content: flex-end;">
      <button type="button" class="btn btn-outline" onclick="closeAuditModal()">إغلاق</button>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  function viewAuditDetails(log) {
    document.getElementById('auditModalTitle').innerText = 'عملية #' + log.id + ' — ' + log.action;
    let details = log.details;
    try {
      if (typeof details === 'string') {
        details = JSON.parse(details);
      }
      document.getElementById('auditModalDetails').innerText = JSON.stringify(details, null, 2);
    } catch(e) {
      document.getElementById('auditModalDetails').innerText = log.details || 'لا توجد بيانات إضافية.';
    }
    document.getElementById('auditModal').classList.add('active');
  }
  function closeAuditModal() {
    document.getElementById('auditModal').classList.remove('active');
  }
</script>
@endsection
