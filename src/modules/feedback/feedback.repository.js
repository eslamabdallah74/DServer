const BaseRepository = require('../../core/BaseRepository');

class FeedbackRepository extends BaseRepository {
  constructor() {
    super('direct_feedback');
  }

  // ── Direct Feedback ────────────────────────────────────────────────────────
  async insertDirectFeedback(data) {
    const { feedbackId, deviceId, category, appVersion, deviceInfo, message } = data;
    const sql = `
      INSERT INTO direct_feedback 
        (feedback_id, device_id, category, app_version, device_info, message)
      VALUES (?, ?, ?, ?, ?, ?)
    `;
    await this.execute(sql, [
      feedbackId,
      deviceId || null,
      category || 'general',
      appVersion || null,
      typeof deviceInfo === 'object' ? JSON.stringify(deviceInfo) : deviceInfo,
      message,
    ]);
    return { success: true, feedbackId };
  }

  async listDirectFeedback({ category = '', limit = 50, offset = 0 } = {}) {
    const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
    const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

    let whereClause = '';
    const params = [];

    if (category.trim()) {
      whereClause = 'WHERE category = ?';
      params.push(category.trim());
    }

    const total = await this.count(whereClause, params);

    const selectSql = `
      SELECT * FROM direct_feedback 
      ${whereClause} 
      ORDER BY created_at DESC 
      LIMIT ? OFFSET ?
    `;
    params.push(parsedLimit, parsedOffset);
    const rows = await this.query(selectSql, params);

    const feedback = rows.map(r => ({
      feedbackId: r.feedback_id,
      deviceId: r.device_id,
      category: r.category,
      appVersion: r.app_version,
      deviceInfo: r.device_info,
      message: r.message,
      createdAt: r.created_at,
    }));

    return { feedback, total };
  }

  async deleteDirectFeedback(feedbackId) {
    await this.execute('DELETE FROM direct_feedback WHERE feedback_id = ?', [feedbackId]);
    return true;
  }

  // ── Match Ratings ──────────────────────────────────────────────────────────
  async insertMatchRating(data) {
    const { ratingId, matchId, deviceId, rating, feedbackCategory, comment } = data;
    const sql = `
      INSERT INTO match_ratings 
        (rating_id, match_id, device_id, rating, feedback_category, comment)
      VALUES (?, ?, ?, ?, ?, ?)
    `;
    await this.execute(sql, [
      ratingId,
      matchId,
      deviceId || null,
      parseInt(rating, 10),
      feedbackCategory || 'gameplay',
      comment || null,
    ]);
    return { success: true, ratingId };
  }

  async listMatchRatings({ ratingFilter, limit = 50, offset = 0 } = {}) {
    const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
    const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

    let whereClause = '';
    const params = [];

    if (ratingFilter !== undefined && ratingFilter !== '') {
      whereClause = 'WHERE rating = ?';
      params.push(parseInt(ratingFilter, 10));
    }

    const countSql = `SELECT COUNT(*) as total, AVG(rating) as avgRating FROM match_ratings ${whereClause}`;
    const countRows = await this.query(countSql, params);
    const total = countRows[0]?.total || 0;
    const averageRating = parseFloat(countRows[0]?.avgRating || 0).toFixed(1);

    const selectSql = `
      SELECT * FROM match_ratings 
      ${whereClause} 
      ORDER BY created_at DESC 
      LIMIT ? OFFSET ?
    `;
    params.push(parsedLimit, parsedOffset);
    const rows = await this.query(selectSql, params);

    const ratings = rows.map(r => ({
      ratingId: r.rating_id,
      matchId: r.match_id,
      deviceId: r.device_id,
      rating: r.rating,
      feedbackCategory: r.feedback_category,
      comment: r.comment,
      createdAt: r.created_at,
    }));

    return { ratings, total, averageRating };
  }

  async deleteMatchRating(ratingId) {
    await this.execute('DELETE FROM match_ratings WHERE rating_id = ?', [ratingId]);
    return true;
  }
}

module.exports = FeedbackRepository;
