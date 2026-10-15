@extends('admin.layout')

@section('title', 'بلاغات الأخطاء والمشاكل')
@section('header_title')
  <i class="fa-solid fa-bug" style="color: var(--gold);"></i>
  <span>بلاغات الأخطاء والمشاكل التقنية</span>
@endsection

@section('content')
<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <a href="{{ route('admin.issues') }}" class="btn {{ !request('severity') ? 'btn-gold' : 'btn-outline' }}">الكل</a>
      <a href="{{ route('admin.issues', ['severity' => 'critical']) }}" class="btn {{ request('severity') == 'critical' ? 'btn-red' : 'btn-outline' }}">
        <i class="fa-solid fa-triangle-exclamation"></i> حرج (Critical)
      </a>
      <a href="{{ route('admin.issues', ['severity' => 'high']) }}" class="btn {{ request('severity') == 'high' ? 'btn-gold' : 'btn-outline' }}">
        <i class="fa-solid fa-circle-exclamation"></i> عالي (High)
      </a>
      <a href="{{ route('admin.issues', ['severity' => 'medium']) }}" class="btn {{ request('severity') == 'medium' ? 'btn-blue' : 'btn-outline' }}">
        متوسط (Medium)
      </a>
      <a href="{{ route('admin.issues', ['severity' => 'low']) }}" class="btn {{ request('severity') == 'low' ? 'btn-outline' : 'btn-outline' }}">
        منخفض (Low)
      </a>
    </div>
    <span style="font-size: 13px; color: var(--text-muted);">إجمالي البلاغات: {{ $issues->total() }}</span>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-list-check"></i> سجل البلاغات المرفوعة من التطبيق</h3>
  </div>

  @if($issues->isEmpty())
    <div style="text-align: center; padding: 48px; color: var(--text-muted);">
      <i class="fa-solid fa-circle-check" style="font-size: 42px; color: var(--green); margin-bottom: 12px; display: block;"></i>
      لا توجد بلاغات أخطاء مسجلة حالياً بهذا التصنيف.
    </div>
  @else
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>الخطورة</th>
            <th>النوع</th>
            <th>الوصف / الخطأ</th>
            <th>إصدار التطبيق</th>
            <th>معرف اللاعب</th>
            <th>التاريخ والوقت</th>
            <th>التفاصيل</th>
          </tr>
        </thead>
        <tbody>
          @foreach($issues as $issue)
            <tr>
              <td>#{{ $issue->id }}</td>
              <td>
                @if($issue->severity === 'critical')
                  <span class="badge badge-red"><i class="fa-solid fa-fire"></i> حرج</span>
                @elseif($issue->severity === 'high')
                  <span class="badge badge-gold"><i class="fa-solid fa-triangle-exclamation"></i> عالي</span>
                @elseif($issue->severity === 'medium')
                  <span class="badge badge-blue">متوسط</span>
                @else
                  <span class="badge badge-outline">منخفض</span>
                @endif
              </td>
              <td style="font-weight: 700;">{{ $issue->issue_type }}</td>
              <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $issue->description }}">
                {{ $issue->description }}
              </td>
              <td><code style="background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px;">{{ $issue->app_version ?: 'N/A' }}</code></td>
              <td><span style="font-family: monospace; color: var(--text-muted);">{{ $issue->player_id ?: 'غير مسجل' }}</span></td>
              <td style="font-size: 12px; color: var(--text-muted);">{{ $issue->created_at ? $issue->created_at->format('Y/m/d H:i') : '-' }}</td>
              <td>
                <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;" onclick='viewIssueDetails(@json($issue))'>
                  <i class="fa-solid fa-eye"></i> عرض
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div style="margin-top: 20px;">
      {{ $issues->withQueryString()->links() }}
    </div>
  @endif
</div>

<!-- Issue Details Modal -->
<div class="modal" id="issueModal">
  <div class="modal-box" style="max-width: 680px;">
    <div class="card-header">
      <h3 class="card-title" id="issueModalTitle"><i class="fa-solid fa-bug"></i> تفاصيل الخطأ</h3>
      <button type="button" onclick="closeIssueModal()" style="background: none; border: none; color: #999; font-size: 20px; cursor: pointer;">&times;</button>
    </div>
    <div style="margin-bottom: 16px;">
      <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">الوصف:</div>
      <div id="issueModalDesc" style="background: rgba(0,0,0,0.4); padding: 12px; border-radius: 8px; font-size: 13px; line-height: 1.6; word-break: break-word;"></div>
    </div>
    <div style="margin-bottom: 16px;">
      <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">معلومات الجهاز والبيئة (Device Info):</div>
      <pre id="issueModalDevice" style="background: rgba(0,0,0,0.5); padding: 10px; border-radius: 8px; font-size: 11px; max-height: 140px; overflow-y: auto; color: #a78bfa; direction: ltr; text-align: left;"></pre>
    </div>
    <div style="margin-bottom: 16px;">
      <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">أثر التتبع (Stack Trace):</div>
      <pre id="issueModalStack" style="background: rgba(0,0,0,0.5); padding: 10px; border-radius: 8px; font-size: 11px; max-height: 160px; overflow-y: auto; color: #f87171; direction: ltr; text-align: left;"></pre>
    </div>
    <div style="display: flex; justify-content: flex-end;">
      <button type="button" class="btn btn-outline" onclick="closeIssueModal()">إغلاق</button>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  function viewIssueDetails(issue) {
    document.getElementById('issueModalTitle').innerText = 'تفاصيل بلاغ #' + issue.id + ' (' + issue.issue_type + ')';
    document.getElementById('issueModalDesc').innerText = issue.description || 'لا يوجد وصف إضافي.';
    document.getElementById('issueModalDevice').innerText = typeof issue.device_info === 'object' ? JSON.stringify(issue.device_info, null, 2) : (issue.device_info || 'N/A');
    document.getElementById('issueModalStack').innerText = issue.stack_trace || 'لا يتوفر Stack Trace.';
    document.getElementById('issueModal').classList.add('active');
  }
  function closeIssueModal() {
    document.getElementById('issueModal').classList.remove('active');
  }
</script>
@endsection
