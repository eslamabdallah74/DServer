const { query, isDbConnected } = require('../db');

async function logAdminAction(req, actionType, targetType, targetId, details = {}) {
  if (!isDbConnected()) return;

  const adminUserId = req.user ? req.user.id : 0;
  const adminEmail = req.user ? req.user.email : 'system';
  const ipAddress = req.ip || req.headers['x-forwarded-for'] || req.socket.remoteAddress || 'unknown';

  try {
    await query(
      `INSERT INTO admin_audit_logs 
       (admin_user_id, admin_email, action_type, target_type, target_id, details, ip_address) 
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        adminUserId,
        adminEmail,
        actionType,
        targetType,
        String(targetId),
        JSON.stringify(details),
        ipAddress,
      ]
    );
  } catch (err) {
    console.error('[Audit Log Error] Failed to write audit record:', err.message);
  }
}

async function listAuditLogs({ limit = 50, offset = 0 } = {}) {
  if (!isDbConnected()) return { logs: [], total: 0 };

  const [countRows] = await query('SELECT COUNT(*) as total FROM admin_audit_logs');
  const total = parseInt(countRows[0].total, 10);

  const [rows] = await query(
    'SELECT * FROM admin_audit_logs ORDER BY id DESC LIMIT ? OFFSET ?',
    [parseInt(limit, 10), parseInt(offset, 10)]
  );

  return {
    logs: rows.map(r => ({
      ...r,
      details: typeof r.details === 'string' ? JSON.parse(r.details) : r.details,
    })),
    total,
  };
}

module.exports = {
  logAdminAction,
  listAuditLogs,
};
