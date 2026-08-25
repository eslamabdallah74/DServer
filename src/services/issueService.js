const { getPool } = require('../db/connection');

async function logAppIssue(data) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  const {
    issueId,
    severity = 'error',
    page = null,
    method = null,
    location = null,
    message,
    errorDetails = null,
    stackTrace = null,
    playerId = null,
    playerName = null,
    appVersion = null,
    deviceInfo = null,
    context = null,
    formattedDate = null,
  } = data;

  if (!issueId || !message) {
    throw new Error('issueId and message are required');
  }

  const query = `
    INSERT INTO app_issues 
      (issue_id, severity, page, method, location, message, error_details, stack_trace, player_id, player_name, app_version, device_info, context, formatted_date)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `;

  await pool.query(query, [
    issueId,
    ['warning', 'error', 'critical'].includes(severity) ? severity : 'error',
    page,
    method,
    location,
    message,
    typeof errorDetails === 'object' ? JSON.stringify(errorDetails) : errorDetails,
    stackTrace,
    playerId,
    playerName,
    appVersion,
    typeof deviceInfo === 'object' ? JSON.stringify(deviceInfo) : deviceInfo,
    context ? JSON.stringify(context) : null,
    formattedDate,
  ]);

  return { success: true, issueId };
}

async function listAppIssues({ severity = '', page = '', limit = 50, offset = 0 } = {}) {
  const pool = getPool();
  if (!pool) return { issues: [], total: 0, criticalCount: 0 };

  const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
  const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

  const whereConditions = [];
  const params = [];

  if (severity.trim()) {
    whereConditions.push('severity = ?');
    params.push(severity.trim());
  }

  if (page.trim()) {
    whereConditions.push('page LIKE ?');
    params.push(`%${page.trim()}%`);
  }

  const whereClause = whereConditions.length > 0 ? `WHERE ${whereConditions.join(' AND ')}` : '';

  const countQuery = `SELECT COUNT(*) as total FROM app_issues ${whereClause}`;
  const [countRows] = await pool.query(countQuery, params);
  const total = countRows[0].total;

  const [critRows] = await pool.query("SELECT COUNT(*) as critTotal FROM app_issues WHERE severity = 'critical'");
  const criticalCount = critRows[0].critTotal || 0;

  const selectQuery = `
    SELECT * FROM app_issues 
    ${whereClause} 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
  `;
  params.push(parsedLimit, parsedOffset);
  const [rows] = await pool.query(selectQuery, params);

  const issues = rows.map(r => ({
    issueId: r.issue_id,
    severity: r.severity,
    page: r.page,
    method: r.method,
    location: r.location,
    message: r.message,
    errorDetails: r.error_details,
    stackTrace: r.stack_trace,
    playerId: r.player_id,
    playerName: r.player_name,
    appVersion: r.app_version,
    deviceInfo: r.device_info,
    context: typeof r.context === 'string' ? JSON.parse(r.context) : r.context,
    formattedDate: r.formatted_date,
    createdAt: r.created_at,
  }));

  return { issues, total, criticalCount };
}

async function deleteAppIssue(issueId) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  await pool.query('DELETE FROM app_issues WHERE issue_id = ?', [issueId]);
  return true;
}

async function clearAllAppIssues() {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  await pool.query('TRUNCATE TABLE app_issues');
  return true;
}

module.exports = {
  logAppIssue,
  listAppIssues,
  deleteAppIssue,
  clearAllAppIssues,
};
