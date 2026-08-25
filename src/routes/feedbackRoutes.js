const express = require('express');
const {
  submitDirectFeedback,
  listDirectFeedback,
  deleteDirectFeedback,
  submitMatchRating,
  listMatchRatings,
  deleteMatchRating,
} = require('../services/feedbackService');
const { authenticateAdmin } = require('../middleware/auth');

const router = express.Router();

// ── Client Submission Endpoints ──────────────────────────────────────────────
router.post('/direct', async (req, res, next) => {
  try {
    const result = await submitDirectFeedback(req.body);
    return res.status(201).json(result);
  } catch (err) {
    next(err);
  }
});

router.post('/rating', async (req, res, next) => {
  try {
    const result = await submitMatchRating(req.body);
    return res.status(201).json(result);
  } catch (err) {
    next(err);
  }
});

// ── Admin Protected Endpoints ───────────────────────────────────────────────
router.use(authenticateAdmin);

// Direct Feedback Management
router.get('/direct', async (req, res, next) => {
  try {
    const { category = '', limit = 50, offset = 0 } = req.query;
    const result = await listDirectFeedback({ category, limit, offset });
    return res.json(result);
  } catch (err) {
    next(err);
  }
});

router.delete('/direct/:feedbackId', async (req, res, next) => {
  try {
    await deleteDirectFeedback(req.params.feedbackId);
    return res.json({ success: true, message: 'تم حذف الملاحظة بنجاح.' });
  } catch (err) {
    next(err);
  }
});

// Match Ratings Management
router.get('/ratings', async (req, res, next) => {
  try {
    const { ratingFilter, limit = 50, offset = 0 } = req.query;
    const result = await listMatchRatings({ ratingFilter, limit, offset });
    return res.json(result);
  } catch (err) {
    next(err);
  }
});

router.delete('/ratings/:ratingId', async (req, res, next) => {
  try {
    await deleteMatchRating(req.params.ratingId);
    return res.json({ success: true, message: 'تم حذف تقييم المباراة بنجاح.' });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
