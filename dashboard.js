/**
 * dashboard.js — HTML/CSS Status Dashboard for Deceit Online Server
 */

function getDashboardHtml(serverData) {
  const { activeRoomsCount, totalPlayersCount, rooms, uptimeSeconds } = serverData;

  const formatUptime = (sec) => {
    const hrs = Math.floor(sec / 3600);
    const mins = Math.floor((sec % 3600) / 60);
    const secs = Math.floor(sec % 60);
    return `${hrs}h ${mins}m ${secs}s`;
  };

  return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>خادم لعبة ديسيت (Deceit Server Status)</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-dark: #09070F;
      --card-bg: rgba(22, 17, 34, 0.75);
      --card-border: rgba(217, 184, 106, 0.25);
      --gold: #D9B86A;
      --gold-glow: rgba(217, 184, 106, 0.3);
      --crimson: #C93B4E;
      --emerald: #00E676;
      --silver: #A0A5B5;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Tajawal', -apple-system, BlinkMacSystemFont, sans-serif;
      background-color: var(--bg-dark);
      background-image: 
        radial-gradient(circle at 50% 0%, rgba(217, 184, 106, 0.08) 0%, transparent 70%),
        radial-gradient(circle at 100% 100%, rgba(201, 59, 78, 0.05) 0%, transparent 50%);
      color: #F0F2F8;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 40px 20px;
    }

    .container {
      width: 100%;
      max-width: 960px;
    }

    /* Header */
    header {
      text-align: center;
      margin-bottom: 36px;
    }

    .logo-container {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
    }

    .logo-icon {
      font-size: 42px;
      filter: drop-shadow(0 0 16px var(--gold-glow));
    }

    h1 {
      font-size: 36px;
      font-weight: 900;
      background: linear-gradient(135deg, #FFF, var(--gold));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      letter-spacing: 1px;
    }

    p.subtitle {
      color: var(--silver);
      font-size: 15px;
      margin-top: 4px;
    }

    /* Status Pulse Banner */
    .status-banner {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: rgba(0, 230, 118, 0.1);
      border: 1px solid rgba(0, 230, 118, 0.3);
      padding: 8px 18px;
      border-radius: 30px;
      margin-top: 14px;
      font-size: 14px;
      color: var(--emerald);
      font-weight: 700;
    }

    .pulse-dot {
      width: 10px;
      height: 10px;
      background-color: var(--emerald);
      border-radius: 50%;
      box-shadow: 0 0 10px var(--emerald);
      animation: pulse 1.8s infinite;
    }

    @keyframes pulse {
      0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 230, 118, 0.7); }
      70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(0, 230, 118, 0); }
      100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 230, 118, 0); }
    }

    /* Stats Cards Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--card-bg);
      backdrop-filter: blur(12px);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0,0,0,0.4);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-4px);
      border-color: var(--gold);
    }

    .stat-value {
      font-size: 32px;
      font-weight: 900;
      color: var(--gold);
      margin-top: 6px;
    }

    .stat-label {
      font-size: 13px;
      color: var(--silver);
      font-weight: 700;
    }

    /* Section Header */
    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--gold);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Table Container */
    .table-card {
      background: var(--card-bg);
      backdrop-filter: blur(12px);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: right;
    }

    th, td {
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    th {
      background: rgba(0,0,0,0.3);
      color: var(--silver);
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: rgba(217, 184, 106, 0.04);
    }

    .room-code {
      font-weight: 900;
      font-size: 16px;
      letter-spacing: 2px;
      color: #FFF;
      background: rgba(217, 184, 106, 0.15);
      padding: 4px 10px;
      border-radius: 6px;
      border: 1px solid rgba(217, 184, 106, 0.3);
      display: inline-block;
    }

    .badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      display: inline-block;
    }

    .badge-lobby { background: rgba(0, 230, 118, 0.15); color: #00E676; border: 1px solid rgba(0, 230, 118, 0.3); }
    .badge-night { background: rgba(191, 212, 255, 0.15); color: #BFD4FF; border: 1px solid rgba(191, 212, 255, 0.3); }
    .badge-morning { background: rgba(255, 201, 120, 0.15); color: #FFC978; border: 1px solid rgba(255, 201, 120, 0.3); }
    .badge-day { background: rgba(217, 184, 106, 0.15); color: #D9B86A; border: 1px solid rgba(217, 184, 106, 0.3); }
    .badge-voting { background: rgba(201, 59, 78, 0.15); color: #C93B4E; border: 1px solid rgba(201, 59, 78, 0.3); }

    .empty-state {
      padding: 40px;
      text-align: center;
      color: var(--silver);
    }

    /* Footer */
    footer {
      margin-top: 40px;
      text-align: center;
      font-size: 13px;
      color: var(--silver);
      opacity: 0.7;
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div class="logo-container">
        <span class="logo-icon">🏰</span>
        <h1>خادم لعبة ديسيت (Deceit Online)</h1>
      </div>
      <p class="subtitle">Authoritative Game Server & Socket.IO Engine</p>
      <div class="status-banner">
        <span class="pulse-dot"></span>
        الخادم يعمل بكفاءة عالية (Online)
      </div>
    </header>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">الغرف النشطة</div>
        <div class="stat-value" id="active-rooms">${activeRoomsCount}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">اللاعبون المتصلون</div>
        <div class="stat-value" id="total-players">${totalPlayersCount}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">مدة التشغيل</div>
        <div class="stat-value" id="uptime">${formatUptime(uptimeSeconds)}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">منفذ الاتصال</div>
        <div class="stat-value">3001</div>
      </div>
    </div>

    <div class="section-title">
      <span>🎮</span> الغرف الحالية في الخادم
    </div>

    <div class="table-card">
      <table id="rooms-table">
        <thead>
          <tr>
            <th>رمز الغرفة</th>
            <th>المرحلة الحالية</th>
            <th>الجولة</th>
            <th>اللاعبون المتصلون</th>
          </tr>
        </thead>
        <tbody id="rooms-tbody">
          ${rooms.length === 0 ? `
            <tr>
              <td colspan="4" class="empty-state">
                لا توجد غرف نشطة حالياً. انشئ غرفة جديدة من التطبيق! 🎲
              </td>
            </tr>
          ` : rooms.map(r => `
            <tr>
              <td><span class="room-code">${r.roomCode}</span></td>
              <td><span class="badge badge-${r.phase.toLowerCase()}">${r.phase}</span></td>
              <td>${r.round}</td>
              <td>${r.connected} / ${r.players}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>

    <footer>
      Deceit Online Server v2.0 • Powered by Node.js & Socket.IO
    </footer>
  </div>

  <script>
    // Auto-refresh stats every 4 seconds
    setInterval(async () => {
      try {
        const res = await fetch('/api/status');
        const data = await res.json();
        
        document.getElementById('active-rooms').innerText = data.activeRooms;
        document.getElementById('total-players').innerText = data.totalPlayers;
        document.getElementById('uptime').innerText = data.uptime;
        
        const tbody = document.getElementById('rooms-tbody');
        if (data.rooms.length === 0) {
          tbody.innerHTML = \`
            <tr>
              <td colspan="4" class="empty-state">
                لا توجد غرف نشطة حالياً. انشئ غرفة جديدة من التطبيق! 🎲
              </td>
            </tr>
          \`;
        } else {
          tbody.innerHTML = data.rooms.map(r => \`
            <tr>
              <td><span class="room-code">\${r.roomCode}</span></td>
              <td><span class="badge badge-\${r.phase.toLowerCase()}">\${r.phase}</span></td>
              <td>\${r.round}</td>
              <td>\${r.connected} / \${r.players}</td>
            </tr>
          \`).join('');
        }
      } catch (err) {
        console.error('Refresh error:', err);
      }
    }, 4000);
  </script>
</body>
</html>`;
}

module.exports = { getDashboardHtml };
