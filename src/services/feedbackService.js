const { getPool } = require('../db/connection');

// ── Direct Feedback Methods ──────────────────────────────────────────────────

async function submitDirectFeedback(data) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  const {
    feedbackId,
    deviceId = null,
    category = 'general',
    appVersion = null,
    deviceInfo = null,
    message,
  } = data;

  if (!feedbackId || !message) {
    throw new Error('feedbackId and message are required');
  }

  const query = `
    INSERT INTO direct_feedback 
      (feedback_id, device_id, category, app_version, device_info, message)
    VALUES (?, ?, ?, ?, ?, ?)
  `;

  await pool.query(query, [
    feedbackId,
    deviceId,
    category,
    appVersion,
    typeof deviceInfo === 'object' ? JSON.stringify(deviceInfo) : deviceInfo,
    message,
  ]);

  return { success: true, feedbackId };
}

async function listDirectFeedback({ category = '', limit = 50, offset = 0 } = {}) {
  const pool = getPool();
  if (!pool) return { feedback: [], total: 0 };

  const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
  const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

  let whereClause = '';
  const params = [];

  if (category.trim()) {
    whereClause = 'WHERE category = ?';
    params.push(category.trim());
  }

  const countQuery = `SELECT COUNT(*) as total FROM direct_feedback ${whereClause}`;
  const [countRows] = await pool.query(countQuery, params);
  const total = countRows[0].total;

  const selectQuery = `
    SELECT * FROM direct_feedback 
    ${whereClause} 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
  `;
  params.push(parsedLimit, parsedOffset);
  const [rows] = await pool.query(selectQuery, params);

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

async function deleteDirectFeedback(feedbackId) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  await pool.query('DELETE FROM direct_feedback WHERE feedback_id = ?', [feedbackId]);
  return true;
}

// ── Match Rating Methods ─────────────────────────────────────────────────────

async function submitMatchRating(data) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  const {
    ratingId,
    matchId,
    deviceId = null,
    rating,
    feedbackCategory = 'gameplay',
    comment = null,
  } = data;

  if (!ratingId || !matchId || rating === undefined) {
    throw new Error('ratingId, matchId, and rating are required');
  }

  const query = `
    INSERT INTO match_ratings 
      (rating_id, match_id, device_id, rating, feedback_category, comment)
    VALUES (?, ?, ?, ?, ?, ?)
  `;

  await pool.query(query, [
    ratingId,
    matchId,
    deviceId,
    parseInt(rating, 10),
    feedbackCategory,
    comment,
  ]);

  return { success: true, ratingId };
}

async function listMatchRatings({ ratingFilter, limit = 50, offset = 0 } = {}) {
  const pool = getPool();
  if (!pool) return { ratings: [], total: 0, averageRating: 0 };

  const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
  const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

  let whereClause = '';
  const params = [];

  if (ratingFilter !== undefined && ratingFilter !== '') {
    whereClause = 'WHERE rating = ?';
    params.push(parseInt(ratingFilter, 10));
  }

  const [countRows] = await pool.query(`SELECT COUNT(*) as total, AVG(rating) as avgRating FROM match_ratings ${whereClause}`, params);
  const total = countRows[0].total || 0;
  const averageRating = parseFloat(countRows[0].avgRating || 0).toFixed(1);

  const selectQuery = `
    SELECT * FROM match_ratings 
    ${whereClause} 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
  `;
  params.push(parsedLimit, parsedOffset);
  const [rows] = await pool.query(selectQuery, params);

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

async function deleteMatchRating(ratingId) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  await pool.query('DELETE FROM match_ratings WHERE rating_id = ?', [ratingId]);
  return true;
}

module.exports = {
  submitDirectFeedback,
  listDirectFeedback,
  deleteDirectFeedback,
  submitMatchRating,
  listMatchRatings,
  deleteMatchRating,
};
