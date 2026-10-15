@extends('admin.layout')

@section('title', 'مراقبة استهلاك Pusher')
@section('header_title')
  <i class="fa-solid fa-satellite-dish" style="color: var(--gold);"></i> مراقبة استهلاك واتصالات Pusher Channels
@endsection

@section('content')
<!-- Pulse Status Bar & Controls -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
  <div style="display: flex; align-items: center; gap: 12px;">
    <span class="badge {{ ($ping['success'] ?? false) ? 'badge-green' : 'badge-red' }}" id="liveConnectionBadge">
      <i class="fa-solid fa-circle" style="font-size: 8px; animation: pulse 1.5s infinite;"></i>
      <span id="liveConnectionText">{{ ($ping['success'] ?? false) ? 'متصل بسحابة Pusher' : 'غير متصل' }}</span>
    </span>
    <span class="badge badge-gold">
      <i class="fa-solid fa-server"></i> Cluster: <strong>{{ $stats['config']['cluster'] }}</strong>
    </span>
    <span class="badge badge-blue">
      <i class="fa-solid fa-fingerprint"></i> App ID: <strong>{{ $stats['config']['app_id'] }}</strong>
    </span>
    <span style="font-size: 12px; color: var(--text-muted);" id="lastSyncTime">
      آخر تحديث: {{ date('H:i:s') }}
    </span>
  </div>

  <div style="display: flex; align-items: center; gap: 10px;">
    <button type="button" class="btn btn-outline" style="font-size: 12px;" onclick="triggerPingTest()" id="btnPing">
      <i class="fa-solid fa-bolt"></i> قياس الـ Ping الآن (<span id="pingVal">{{ $ping['latency_ms'] ?? 0 }}</span> ms)
    </button>
    <button type="button" class="btn btn-outline" style="font-size: 12px;" onclick="toggleAutoRefresh()" id="btnToggleAutoRefresh">
      <i class="fa-solid fa-arrows-rotate fa-spin" id="refreshSpinIcon"></i> تحديث تلقائي (مفعل)
    </button>
    <form action="{{ route('admin.pusher.resetStats') }}" method="POST" onsubmit="return confirm('هل أنت متأكد من تصفير إحصائيات استهلاك Pusher؟');" style="display: inline;">
      @csrf
      <button type="submit" class="btn btn-red" style="font-size: 12px;">
        <i class="fa-solid fa-trash-can"></i> تصفير الإحصائيات
      </button>
    </form>
  </div>
</div>

<!-- 4 Key Stat Cards -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); margin-bottom: 24px;">
  <!-- Card 1: Daily Message Quota -->
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <span style="font-size: 12px; color: var(--text-muted); font-weight: 700;">الاستهلاك اليومي (Messages)</span>
      <span class="badge {{ $stats['today']['quota_percentage'] > 85 ? 'badge-red' : ($stats['today']['quota_percentage'] > 60 ? 'badge-gold' : 'badge-green') }}" id="quotaBadge">
        {{ $stats['today']['quota_percentage'] }}%
      </span>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 8px;" id="msgsTodayVal">
      {{ number_format($stats['today']['messages']) }} <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">/ {{ number_format($stats['config']['daily_limit']) }}</span>
    </div>
    <!-- Progress Bar -->
    <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.08); border-radius: 4px; overflow: hidden; margin-bottom: 8px;">
      <div id="quotaProgressBar" style="width: {{ min(100, $stats['today']['quota_percentage']) }}%; height: 100%; background: {{ $stats['today']['quota_percentage'] > 85 ? '#ef4444' : ($stats['today']['quota_percentage'] > 60 ? '#f59e0b' : '#10b981') }}; transition: width 0.4s ease;"></div>
    </div>
    <div style="font-size: 11px; color: var(--text-muted); display: flex; justify-content: space-between;">
      <span>المتبقي اليوم: <strong style="color: var(--gold);" id="remainingVal">{{ number_format($stats['today']['remaining']) }}</strong></span>
      <span>الخطة: 200k مجاني</span>
    </div>
  </div>

  <!-- Card 2: Bandwidth Consumed -->
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <span style="font-size: 12px; color: var(--text-muted); font-weight: 700;">حجم البيانات المرسلة (Bandwidth)</span>
      <i class="fa-solid fa-network-wired" style="color: #60a5fa;"></i>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: #60a5fa; margin-bottom: 8px;" id="bytesTodayVal">
      {{ $stats['today']['bytes_formatted'] }}
    </div>
    <div style="font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between;">
      <span>إجمالي الحجم التراكمي:</span>
      <strong style="color: #fff;" id="bytesTotalVal">{{ $stats['totals']['bytes_formatted'] }}</strong>
    </div>
  </div>

  <!-- Card 3: Active Occupied Channels -->
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <span style="font-size: 12px; color: var(--text-muted); font-weight: 700;">القنوات النشطة على Pusher</span>
      <i class="fa-solid fa-tower-broadcast" style="color: var(--gold);"></i>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: var(--gold); margin-bottom: 8px;" id="occupiedChannelsCount">
      {{ count($channelsApi['channels'] ?? []) }}
    </div>
    <div style="font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between;">
      <span>غرف اللعبة الحية بالسيرفر:</span>
      <strong style="color: #fff;" id="activeRoomsCountVal">{{ $stats['active_rooms_count'] }} غرفة</strong>
    </div>
  </div>

  <!-- Card 4: Success Rate & Errors -->
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <span style="font-size: 12px; color: var(--text-muted); font-weight: 700;">معدل نجاح الإرسال (Reliability)</span>
      <i class="fa-solid fa-shield-check" style="color: #10b981;"></i>
    </div>
    <div style="font-size: 26px; font-weight: 900; color: #10b981; margin-bottom: 8px;" id="successRateVal">
      {{ $stats['totals']['success_rate'] }}%
    </div>
    <div style="font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between;">
      <span>الأخطاء المسجلة:</span>
      <strong style="color: {{ $stats['totals']['errors'] > 0 ? '#ef4444' : '#10b981' }};" id="errorsTotalVal">{{ $stats['totals']['errors'] }} فشل</strong>
    </div>
  </div>
</div>

<!-- Two-Column Section: Event Distribution & Diagnostic Tools -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 24px; margin-bottom: 24px;">
  <!-- Left: Event Types Distribution Breakdown -->
  <div class="card">
    <div class="card-header" style="margin-bottom: 16px;">
      <h3 class="card-title">
        <i class="fa-solid fa-chart-pie" style="color: var(--gold);"></i> توزيع الأحداث المرسلة (Events Breakdown)
      </h3>
      <span class="badge badge-gold" id="totalMsgsBadge">{{ number_format($stats['totals']['messages']) }} إجمالي</span>
    </div>

    @if(empty($stats['events_breakdown']))
      <div style="text-align: center; padding: 32px 16px; color: var(--text-muted); font-size: 13px;">
        <i class="fa-solid fa-inbox" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
        <p>لم يتم تسجيل أحداث حتى الآن. ستظهر الأحداث فور تشغيل المباريات أو إرسال اختبار.</p>
      </div>
    @else
      <div style="display: flex; flex-direction: column; gap: 12px;" id="eventsBreakdownList">
        @php
          $maxEventCount = max(array_values($stats['events_breakdown']) ?: [1]);
          $totalEventsCount = array_sum($stats['events_breakdown']) ?: 1;
        @endphp
        @foreach($stats['events_breakdown'] as $evtName => $evtCount)
          @php
            $percentage = round(($evtCount / $totalEventsCount) * 100, 1);
            $barWidth = round(($evtCount / $maxEventCount) * 100);
          @endphp
          <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 10px 14px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 13px; font-weight: 700; color: #fff;">
                <i class="fa-solid fa-bolt" style="color: var(--gold); font-size: 11px;"></i> {{ $evtName }}
              </span>
              <span style="font-size: 12px; color: var(--text-muted);">
                <strong>{{ number_format($evtCount) }}</strong> رسالة ({{ $percentage }}%)
              </span>
            </div>
            <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden;">
              <div style="width: {{ $barWidth }}%; height: 100%; background: linear-gradient(90deg, #f59e0b, #3b82f6); border-radius: 3px;"></div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <!-- Right: Interactive Diagnostic & Broadcast Testing Tools -->
  <div style="display: flex; flex-direction: column; gap: 24px;">
    <!-- Tool 1: Live Test Event Broadcast -->
    <div class="card">
      <div class="card-header" style="margin-bottom: 14px;">
        <h3 class="card-title">
          <i class="fa-solid fa-paper-plane" style="color: #60a5fa;"></i> إرسال حدث تجريبي مباشر (Test Broadcast)
        </h3>
        <span class="badge badge-blue"><i class="fa-solid fa-bolt"></i> فوري</span>
      </div>
      <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">
        يمكنك إرسال حدث اختباري فوراً للتأكد من استجابة سحابة Pusher ووصول البث إلى أي غرفة حية.
      </p>

      <form id="testBroadcastForm" onsubmit="handleTestBroadcast(event)">
        @csrf
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
          <div>
            <label style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">القناة (Channel):</label>
            <input type="text" name="channel" id="testChannelInput" class="form-control" value="global" placeholder="e.g. room-XXXX or global" required>
          </div>
          <div>
            <label style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">اسم الحدث (Event Name):</label>
            <input type="text" name="event" id="testEventInput" class="form-control" value="ping" placeholder="e.g. s_test_ping or ping" required>
          </div>
        </div>
        <div style="margin-bottom: 14px;">
          <label style="font-size: 12px; color: var(--text-muted); display: block; margin-bottom: 4px;">نص الرسالة (Message Payload):</label>
          <input type="text" name="message" id="testMessageInput" class="form-control" value="اختبار بث من لوحة الإدارة" placeholder="نص الرسالة الاختيارية">
        </div>
        <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;" id="btnSubmitTestEvent">
          <i class="fa-solid fa-paper-plane"></i> إرسال البث الآن والتحقق من الاستجابة
        </button>
      </form>
      <div id="testEventResultAlert" style="display: none; margin-top: 12px; padding: 10px 14px; border-radius: 8px; font-size: 12px;"></div>
    </div>

    <!-- Tool 2: Pusher Channel Inspector -->
    <div class="card">
      <div class="card-header" style="margin-bottom: 14px;">
        <h3 class="card-title">
          <i class="fa-solid fa-magnifying-glass-chart" style="color: var(--gold);"></i> فاحص القنوات في السحابة (Channel Inspector)
        </h3>
      </div>
      <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">
        استعلم مباشرة من خوادم Pusher REST API عن حالة أي قناة، وحجم المشتركين والاتصالات النشطة بها.
      </p>

      <div style="display: flex; gap: 10px; margin-bottom: 12px;">
        <input type="text" id="inspectChannelInput" class="form-control" placeholder="أدخل اسم القناة، مثلاً room-TEST أو global">
        <button type="button" class="btn btn-outline" onclick="inspectChannel()" id="btnInspect">
          <i class="fa-solid fa-magnifying-glass"></i> فحص
        </button>
      </div>
      <div id="channelInspectResult" style="display: none; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 12px; font-size: 12px;">
      </div>
    </div>
  </div>
</div>

<!-- Full Width: Real-time Live Transmission Stream Log -->
<div class="card">
  <div class="card-header" style="margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 class="card-title">
        <i class="fa-solid fa-list-check" style="color: var(--gold);"></i> سجل الإرسال الحي المباشر (Live Transmission Stream)
      </h3>
      <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
        يعرض آخر العمليات والأحداث التي تم بثها عبر Pusher مع تفاصيل الحجم وزمن الاستجابة.
      </p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
      <input type="text" id="filterTransmissionsInput" oninput="filterTransmissions()" class="form-control" style="width: 220px; font-size: 12px; padding: 6px 12px;" placeholder="بحث بالقناة أو الحدث...">
    </div>
  </div>

  <div style="overflow-x: auto;">
    <table style="width: 100%; border-collapse: collapse; text-align: right; font-size: 13px;" id="transmissionsTable">
      <thead>
        <tr style="border-bottom: 1px solid var(--card-border); color: var(--text-muted); font-size: 12px;">
          <th style="padding: 10px 14px;">الوقت</th>
          <th style="padding: 10px 14px;">القناة (Channel)</th>
          <th style="padding: 10px 14px;">اسم الحدث (Event)</th>
          <th style="padding: 10px 14px;">حجم البيانات</th>
          <th style="padding: 10px 14px;">زمن الاستجابة</th>
          <th style="padding: 10px 14px;">رمز HTTP</th>
          <th style="padding: 10px 14px;">الحالة</th>
        </tr>
      </thead>
      <tbody id="transmissionsTableBody">
        @forelse($stats['recent_transmissions'] as $tx)
          <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.2s;">
            <td style="padding: 10px 14px; color: var(--text-muted); font-size: 11px; white-space: nowrap;">
              {{ $tx['timestamp'] }}
            </td>
            <td style="padding: 10px 14px;">
              <span class="badge badge-gold">{{ $tx['channel'] }}</span>
            </td>
            <td style="padding: 10px 14px; font-weight: 700; color: #fff;">
              {{ $tx['event'] }}
            </td>
            <td style="padding: 10px 14px; color: #60a5fa; font-size: 12px;">
              {{ number_format($tx['bytes']) }} B
            </td>
            <td style="padding: 10px 14px; color: var(--text-muted); font-size: 12px;">
              {{ $tx['duration_ms'] }} ms
            </td>
            <td style="padding: 10px 14px;">
              <span class="badge {{ ($tx['status_code'] >= 200 && $tx['status_code'] < 300) ? 'badge-green' : 'badge-red' }}">
                {{ $tx['status_code'] }}
              </span>
            </td>
            <td style="padding: 10px 14px;">
              @if($tx['success'])
                <span class="badge badge-green"><i class="fa-solid fa-circle-check"></i> نجاح</span>
              @else
                <span class="badge badge-red"><i class="fa-solid fa-circle-xmark"></i> فشل</span>
              @endif
            </td>
          </tr>
        @empty
          <tr id="emptyRow">
            <td colspan="7" style="text-align: center; padding: 32px 14px; color: var(--text-muted);">
              <i class="fa-solid fa-inbox" style="font-size: 28px; margin-bottom: 8px; opacity: 0.5;"></i>
              <p>لا توجد سجلات إرسال حتى الآن.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<style>
  @keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.9); }
  }
</style>

<script>
  let autoRefreshActive = true;
  let refreshTimer = null;

  function toggleAutoRefresh() {
    autoRefreshActive = !autoRefreshActive;
    const btn = document.getElementById('btnToggleAutoRefresh');
    const icon = document.getElementById('refreshSpinIcon');
    if (autoRefreshActive) {
      btn.innerHTML = '<i class="fa-solid fa-arrows-rotate fa-spin" id="refreshSpinIcon"></i> تحديث تلقائي (مفعل)';
      btn.classList.remove('btn-red');
      btn.classList.add('btn-outline');
      startAutoRefresh();
    } else {
      btn.innerHTML = '<i class="fa-solid fa-pause"></i> تحديث تلقائي (متوقف)';
      btn.classList.remove('btn-outline');
      btn.classList.add('btn-red');
      stopAutoRefresh();
    }
  }

  function startAutoRefresh() {
    stopAutoRefresh();
    refreshTimer = setInterval(fetchLiveStats, 3000);
  }

  function stopAutoRefresh() {
    if (refreshTimer) {
      clearInterval(refreshTimer);
      refreshTimer = null;
    }
  }

  async function fetchLiveStats() {
    try {
      const res = await fetch("{{ route('admin.pusher.liveStats') }}", {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.success) return;

      const stats = data.stats;
      const channelsApi = data.channelsApi;

      // Update counters
      document.getElementById('msgsTodayVal').innerHTML =
        `${Number(stats.today.messages).toLocaleString()} <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">/ ${Number(stats.config.daily_limit).toLocaleString()}</span>`;
      document.getElementById('quotaBadge').innerText = `${stats.today.quota_percentage}%`;
      document.getElementById('remainingVal').innerText = Number(stats.today.remaining).toLocaleString();
      document.getElementById('quotaProgressBar').style.width = `${Math.min(100, stats.today.quota_percentage)}%`;

      document.getElementById('bytesTodayVal').innerText = stats.today.bytes_formatted;
      document.getElementById('bytesTotalVal').innerText = stats.totals.bytes_formatted;
      document.getElementById('successRateVal').innerText = `${stats.totals.success_rate}%`;
      document.getElementById('errorsTotalVal').innerText = `${stats.totals.errors} فشل`;
      document.getElementById('activeRoomsCountVal').innerText = `${stats.active_rooms_count} غرفة`;

      if (channelsApi && channelsApi.channels) {
        document.getElementById('occupiedChannelsCount').innerText = Object.keys(channelsApi.channels).length;
      }

      document.getElementById('totalMsgsBadge').innerText = `${Number(stats.totals.messages).toLocaleString()} إجمالي`;
      document.getElementById('lastSyncTime').innerText = `آخر تحديث: ${new Date().toLocaleTimeString('ar-EG')}`;

      // Update Recent Transmissions Table
      if (stats.recent_transmissions && stats.recent_transmissions.length > 0) {
        const tbody = document.getElementById('transmissionsTableBody');
        let html = '';
        stats.recent_transmissions.forEach(tx => {
          const statusClass = (tx.status_code >= 200 && tx.status_code < 300) ? 'badge-green' : 'badge-red';
          const successBadge = tx.success
            ? '<span class="badge badge-green"><i class="fa-solid fa-circle-check"></i> نجاح</span>'
            : '<span class="badge badge-red"><i class="fa-solid fa-circle-xmark"></i> فشل</span>';

          html += `
            <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.2s;">
              <td style="padding: 10px 14px; color: var(--text-muted); font-size: 11px; white-space: nowrap;">${tx.timestamp}</td>
              <td style="padding: 10px 14px;"><span class="badge badge-gold">${escapeHtml(tx.channel)}</span></td>
              <td style="padding: 10px 14px; font-weight: 700; color: #fff;">${escapeHtml(tx.event)}</td>
              <td style="padding: 10px 14px; color: #60a5fa; font-size: 12px;">${Number(tx.bytes).toLocaleString()} B</td>
              <td style="padding: 10px 14px; color: var(--text-muted); font-size: 12px;">${tx.duration_ms} ms</td>
              <td style="padding: 10px 14px;"><span class="badge ${statusClass}">${tx.status_code}</span></td>
              <td style="padding: 10px 14px;">${successBadge}</td>
            </tr>
          `;
        });
        tbody.innerHTML = html;
      }
    } catch (e) {
      // Background poll fail suppressed
    }
  }

  async function triggerPingTest() {
    const btn = document.getElementById('btnPing');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري القياس...';
    btn.disabled = true;

    try {
      const res = await fetch("{{ route('admin.pusher.ping') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json',
        }
      });
      const data = await res.json();
      document.getElementById('pingVal').innerText = data.latency_ms || 0;

      const badge = document.getElementById('liveConnectionBadge');
      const text = document.getElementById('liveConnectionText');
      if (data.success) {
        badge.className = 'badge badge-green';
        text.innerText = `متصل (${data.latency_ms} ms)`;
      } else {
        badge.className = 'badge badge-red';
        text.innerText = 'فشل الاتصال';
      }
    } catch (e) {
      document.getElementById('liveConnectionBadge').className = 'badge badge-red';
      document.getElementById('liveConnectionText').innerText = 'خطأ في الاتصال';
    } finally {
      btn.innerHTML = `<i class="fa-solid fa-bolt"></i> قياس الـ Ping الآن (<span id="pingVal">${document.getElementById('pingVal').innerText}</span> ms)`;
      btn.disabled = false;
    }
  }

  async function handleTestBroadcast(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitTestEvent');
    const alertBox = document.getElementById('testEventResultAlert');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الإرسال...';
    btn.disabled = true;

    const channel = document.getElementById('testChannelInput').value.trim();
    const event = document.getElementById('testEventInput').value.trim();
    const message = document.getElementById('testMessageInput').value.trim();

    try {
      const res = await fetch("{{ route('admin.pusher.testEvent') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ channel, event, message })
      });
      const data = await res.json();

      alertBox.style.display = 'block';
      if (data.success) {
        alertBox.style.background = 'rgba(16, 185, 129, 0.15)';
        alertBox.style.color = '#34d399';
        alertBox.style.border = '1px solid rgba(16, 185, 129, 0.3)';
        alertBox.innerHTML = `<i class="fa-solid fa-circle-check"></i> ${data.message || 'تم إرسال الحدث بنجاح!'}`;
        fetchLiveStats();
      } else {
        alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
        alertBox.style.color = '#f87171';
        alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        alertBox.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> ${data.message || 'تعذر الإرسال إلى Pusher.'}`;
      }
    } catch (err) {
      alertBox.style.display = 'block';
      alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
      alertBox.style.color = '#f87171';
      alertBox.style.border = '1px solid rgba(239, 68, 68, 0.3)';
      alertBox.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> حدث خطأ أثناء الاتصال بالخادم.`;
    } finally {
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> إرسال البث الآن والتحقق من الاستجابة';
      btn.disabled = false;
    }
  }

  async function inspectChannel() {
    const channel = document.getElementById('inspectChannelInput').value.trim();
    if (!channel) return;
    const btn = document.getElementById('btnInspect');
    const box = document.getElementById('channelInspectResult');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    try {
      const res = await fetch(`{{ route('admin.pusher.inspectChannel') }}?channel=${encodeURIComponent(channel)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      box.style.display = 'block';

      if (data.details) {
        box.innerHTML = `
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <strong style="color: var(--gold);"><i class="fa-solid fa-tower-broadcast"></i> ${escapeHtml(channel)}</strong>
            <span class="badge ${data.details.occupied ? 'badge-green' : 'badge-gold'}">${data.details.occupied ? 'قناة مشغولة (Occupied)' : 'فارغة'}</span>
          </div>
          <div style="font-size: 11px; color: var(--text-muted);">
            المشتركين: <strong>${data.details.subscription_count ?? (data.details.occupied ? 'نشط' : '0')}</strong> |
            المستخدمين: <strong>${data.details.user_count ?? '0'}</strong>
          </div>
        `;
      } else {
        box.innerHTML = `
          <span style="color: var(--text-muted);"><i class="fa-solid fa-circle-info"></i> القناة <strong>${escapeHtml(channel)}</strong> غير مسجلة حالياً أو لا يوجد متصلين نشطين بها في سحابة Pusher.</span>
        `;
      }
    } catch (e) {
      box.style.display = 'block';
      box.innerHTML = `<span style="color: #ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> فشل فحص القناة.</span>`;
    } finally {
      btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> فحص';
      btn.disabled = false;
    }
  }

  function filterTransmissions() {
    const filter = document.getElementById('filterTransmissionsInput').value.toLowerCase();
    const rows = document.querySelectorAll('#transmissionsTableBody tr');
    rows.forEach(row => {
      if (row.id === 'emptyRow') return;
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>"']/g, function(m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
  }

  // Start polling when page loads
  document.addEventListener('DOMContentLoaded', () => {
    startAutoRefresh();
  });
</script>
@endsection

