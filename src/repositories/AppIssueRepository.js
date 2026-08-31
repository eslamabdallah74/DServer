const { query, isDbConnected } = require('../core/Database');

class AppIssueRepository {
  static async logIssue({ severity = 'error', message, stackTrace = '', page = '', deviceInfo = {} }) {
    if (!isDbConnected()) return null;

    try {
      const sql = `
        INSERT INTO app_issues (severity, message, stack_trace, page, device_info, created_at)
        VALUES (?, ?, ?, ?, ?, NOW());
      `;
      const [rows, result] = await query(sql, [
        severity,
        message || 'Unknown Error',
        stackTrace,
        page,
        JSON.stringify(deviceInfo),
      ]);
      return result.insertId || (rows[0] && rows[0].id) || null;
    } catch (err) {
      console.error('[AppIssueRepository] logIssue error:', err.message);
      return null;
    }
  }

  static async getRecentIssues(limit = 20) {
    if (!isDbConnected()) return [];

    try {
      const [rows] = await query('SELECT * FROM app_issues ORDER BY created_at DESC LIMIT ?', [limit]);
      return rows;
    } catch (err) {
      console.error('[AppIssueRepository] getRecentIssues error:', err.message);
      return [];
    }
  }
}

module.exports = AppIssueRepository;
