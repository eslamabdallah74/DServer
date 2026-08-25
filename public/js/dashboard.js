document.addEventListener('DOMContentLoaded', () => {
  checkAuthOrRedirect();
  loadAdminProfile();
  loadOverviewStats();
});

let searchTimeout = null;

function loadAdminProfile() {
  const adminUser = JSON.parse(localStorage.getItem('admin_user') || '{}');
  const nameEl = document.getElementById('displayAdminName');
  if (nameEl && adminUser.username) {
    nameEl.textContent = adminUser.username;
  }
}

function switchTab(tabId, btn) {
  document.querySelectorAll('.nav-item button').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

  btn.classList.add('active');
  const targetTab = document.getElementById(`tab-${tabId}`);
  if (targetTab) {
    targetTab.classList.add('active');
  }

  if (tabId === 'overview') loadOverviewStats();
  if (tabId === 'players') loadPlayers();
  if (tabId === 'feedback') loadFeedback();
  if (tabId === 'ratings') loadRatings();
  if (tabId === 'issues') loadIssues();
}

async function loadOverviewStats() {
  try {
    const res = await fetch('/api/stats/overview', { headers: getAuthHeaders() });
    if (!res.ok) return;
    const data = await res.json();

    document.getElementById('statPlayers').textContent = data.playersCount;
    document.getElementById('statFeedback').textContent = data.feedbackCount;
    document.getElementById('statRatings').textContent = `${data.ratingsCount} (★ ${data.averageRating})`;
    document.getElementById('statIssues').textContent = `${data.issuesCount} (${data.criticalCount} حرج)`;
  } catch (err) {
    console.error('Failed to load overview stats:', err);
  }
}

// ── PLAYERS TAB ─────────────────────────────────────────────────────────────
async function loadPlayers() {
  const tbody = document.getElementById('playersTableBody');
  const search = document.getElementById('playerSearchInput').value.trim();

  tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">جاري التحميل...</td></tr>';

  try {
    const res = await fetch(`/api/players?search=${encodeURIComponent(search)}`, { headers: getAuthHeaders() });
    const data = await res.json();

    if (!data.players || data.players.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">لا توجد بيانات لاعبين مسجلة.</td></tr>';
      return;
    }

    tbody.innerHTML = data.players.map(p => `
      <tr>
        <td><code>${escapeHtml(p.playerId)}</code></td>
        <td><strong>${escapeHtml(p.name)}</strong></td>
        <td><span class="badge rating-star">🪙 ${p.coins}</span></td>
        <td>${p.matchesPlayed}</td>
        <td>${p.gamesWon}</td>
        <td><small>${formatDate(p.updatedAt)}</small></td>
        <td>
          <div style="display: flex; gap: 6px;">
            <button class="btn btn-secondary btn-sm" onclick="openEditPlayerModal('${escapeHtml(p.playerId)}', '${escapeHtml(p.name)}', ${p.coins})" title="تعديل">
              <i class="fa-solid fa-pen"></i>
            </button>
            <button class="btn btn-secondary btn-sm" onclick="viewJsonModal('بيانات اللاعب', ${escapeJsonAttr(p)})" title="عرض التفاصيل">
              <i class="fa-solid fa-code"></i>
            </button>
            <button class="btn btn-danger btn-sm" onclick="deletePlayerItem('${escapeHtml(p.playerId)}')" title="حذف">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--status-danger);">فشل تحميل البيانات.</td></tr>';
  }
}

function debouncePlayerSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(loadPlayers, 300);
}

function openEditPlayerModal(id, name, coins) {
  document.getElementById('editPlayerId').value = id;
  document.getElementById('editPlayerName').value = name;
  document.getElementById('editPlayerCoins').value = coins;
  openModal('editPlayerModal');
}

document.getElementById('editPlayerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('editPlayerId').value;
  const name = document.getElementById('editPlayerName').value.trim();
  const coins = parseInt(document.getElementById('editPlayerCoins').value, 10);

  try {
    const res = await fetch(`/api/players/${id}`, {
      method: 'PUT',
      headers: getAuthHeaders(),
      body: JSON.stringify({ name, coins }),
    });
    if (res.ok) {
      closeModal('editPlayerModal');
      loadPlayers();
      loadOverviewStats();
    } else {
      alert('فشل تعديل بيانات اللاعب');
    }
  } catch (err) {
    alert('خطأ في الاتصال بالخادم');
  }
});

async function deletePlayerItem(id) {
  if (!confirm(`هل أنت تأكد من حذف اللاعب ${id}؟`)) return;
  try {
    const res = await fetch(`/api/players/${id}`, { method: 'DELETE', headers: getAuthHeaders() });
    if (res.ok) {
      loadPlayers();
      loadOverviewStats();
    }
  } catch (err) {
    alert('فشل الحذف');
  }
}

// ── DIRECT FEEDBACK TAB ──────────────────────────────────────────────────────
async function loadFeedback() {
  const tbody = document.getElementById('feedbackTableBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">جاري التحميل...</td></tr>';

  try {
    const res = await fetch('/api/feedback/direct', { headers: getAuthHeaders() });
    const data = await res.json();

    if (!data.feedback || data.feedback.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">لا توجد ملاحظات مسجلة.</td></tr>';
      return;
    }

    tbody.innerHTML = data.feedback.map(f => `
      <tr>
        <td><span class="badge warning">${escapeHtml(f.category)}</span></td>
        <td style="max-width: 300px;">${escapeHtml(f.message)}</td>
        <td><small>${escapeHtml(f.appVersion || 'N/A')}</small></td>
        <td><small style="color: var(--text-dim);">${escapeHtml(f.deviceInfo || 'N/A')}</small></td>
        <td><small>${formatDate(f.createdAt)}</small></td>
        <td>
          <button class="btn btn-danger btn-sm" onclick="deleteFeedbackItem('${escapeHtml(f.feedbackId)}')" title="حذف">
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--status-danger);">فشل تحميل البيانات.</td></tr>';
  }
}

async function deleteFeedbackItem(id) {
  if (!confirm('هل تريد حذف هذه الملاحظة؟')) return;
  try {
    const res = await fetch(`/api/feedback/direct/${id}`, { method: 'DELETE', headers: getAuthHeaders() });
    if (res.ok) {
      loadFeedback();
      loadOverviewStats();
    }
  } catch (err) {
    alert('فشل الحذف');
  }
}

// ── MATCH RATINGS TAB ────────────────────────────────────────────────────────
async function loadRatings() {
  const tbody = document.getElementById('ratingsTableBody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">جاري التحميل...</td></tr>';

  try {
    const res = await fetch('/api/feedback/ratings', { headers: getAuthHeaders() });
    const data = await res.json();

    if (!data.ratings || data.ratings.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted);">لا توجد تقييمات مباريات مسجلة.</td></tr>';
      return;
    }

    tbody.innerHTML = data.ratings.map(r => `
      <tr>
        <td><code>${escapeHtml(r.matchId)}</code></td>
        <td><span class="badge rating-star">★ ${r.rating} / 5</span></td>
        <td><small>${escapeHtml(r.feedbackCategory)}</small></td>
        <td style="max-width: 250px;">${escapeHtml(r.comment || 'بدون تعليق')}</td>
        <td><small>${formatDate(r.createdAt)}</small></td>
        <td>
          <button class="btn btn-danger btn-sm" onclick="deleteRatingItem('${escapeHtml(r.ratingId)}')" title="حذف">
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--status-danger);">فشل تحميل البيانات.</td></tr>';
  }
}

async function deleteRatingItem(id) {
  if (!confirm('هل تريد حذف هذا التقييم؟')) return;
  try {
    const res = await fetch(`/api/feedback/ratings/${id}`, { method: 'DELETE', headers: getAuthHeaders() });
    if (res.ok) {
      loadRatings();
      loadOverviewStats();
    }
  } catch (err) {
    alert('فشل الحذف');
  }
}

// ── APP ISSUES TAB ───────────────────────────────────────────────────────────
async function loadIssues() {
  const tbody = document.getElementById('issuesTableBody');
  const severity = document.getElementById('issueSeverityFilter').value;

  tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">جاري التحميل...</td></tr>';

  try {
    const res = await fetch(`/api/issues?severity=${severity}`, { headers: getAuthHeaders() });
    const data = await res.json();

    if (!data.issues || data.issues.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">لا توجد سجلات أخطاء.</td></tr>';
      return;
    }

    tbody.innerHTML = data.issues.map(i => `
      <tr>
        <td><span class="badge ${i.severity}">${i.severity.toUpperCase()}</span></td>
        <td><small><strong>${escapeHtml(i.page || '')}</strong><br><span style="color: var(--text-dim);">${escapeHtml(i.method || '')}</span></small></td>
        <td style="max-width: 250px;">${escapeHtml(i.message)}</td>
        <td><small>${escapeHtml(i.playerName || i.playerId || 'مجهول')}</small></td>
        <td><small>${formatDate(i.createdAt)}</small></td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick="viewJsonModal('تفاصيل الخطأ', ${escapeJsonAttr(i)})">
            <i class="fa-solid fa-code"></i> التفاصيل
          </button>
        </td>
        <td>
          <button class="btn btn-danger btn-sm" onclick="deleteIssueItem('${escapeHtml(i.issueId)}')" title="حذف">
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--status-danger);">فشل تحميل البيانات.</td></tr>';
  }
}

async function deleteIssueItem(id) {
  if (!confirm('هل تريد حذف هذا السجل؟')) return;
  try {
    const res = await fetch(`/api/issues/${id}`, { method: 'DELETE', headers: getAuthHeaders() });
    if (res.ok) {
      loadIssues();
      loadOverviewStats();
    }
  } catch (err) {
    alert('فشل الحذف');
  }
}

async function clearAllIssues() {
  if (!confirm('⚠️ هل تريد بالفعل حذف جميع سجلات الأخطاء بشكل دائم؟')) return;
  try {
    const res = await fetch('/api/issues/clear-all', { method: 'DELETE', headers: getAuthHeaders() });
    if (res.ok) {
      loadIssues();
      loadOverviewStats();
    }
  } catch (err) {
    alert('فشل الحذف الكلي');
  }
}

// ── UTILITY FUNCTIONS ────────────────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('active');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}

function viewJsonModal(title, jsonObj) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalCode').textContent = JSON.stringify(jsonObj, null, 2);
  openModal('detailsModal');
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escapeJsonAttr(obj) {
  return JSON.stringify(obj).replace(/'/g, "&apos;").replace(/"/g, '&quot;');
}

function formatDate(isoStr) {
  if (!isoStr) return '';
  try {
    const d = new Date(isoStr);
    return d.toLocaleString('ar-EG', { dateStyle: 'short', timeStyle: 'short' });
  } catch (_) {
    return isoStr;
  }
}
