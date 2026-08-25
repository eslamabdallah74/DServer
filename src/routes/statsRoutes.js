const express = require('express');
const { getPool } = require('../db/connection');
const { authenticateAdmin } = require('../middleware/auth');

const router = express.Router();

router.use(authenticateAdmin);

router.get('/overview', async (req, res, next) => {
  try {
    const pool = getPool();
    if (!pool) {
      return res.status(503).json({ error: 'DB_OFFLINE', message: 'قاعدة البيانات غير متصلة.' });
    }

    const [playersRow] = await pool.query('SELECT COUNT(*) as total FROM players');
    const [feedbackRow] = await pool.query('SELECT COUNT(*) as total FROM direct_feedback');
    const [ratingsRow] = await pool.query('SELECT COUNT(*) as total, AVG(rating) as avgRating FROM match_ratings');
    const [issuesRow] = await pool.query('SELECT COUNT(*) as total FROM app_issues');
    const [criticalRow] = await pool.query("SELECT COUNT(*) as total FROM app_issues WHERE severity = 'critical'");

    return res.json({
      playersCount: playersRow[0].total || 0,
      feedbackCount: feedbackRow[0].total || 0,
      ratingsCount: ratingsRow[0].total || 0,
      averageRating: parseFloat(ratingsRow[0].avgRating || 0).toFixed(1),
      issuesCount: issuesRow[0].total || 0,
      criticalCount: criticalRow[0].total || 0,
    });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
