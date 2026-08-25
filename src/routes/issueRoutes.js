const express = require('express');
const {
  logAppIssue,
  listAppIssues,
  deleteAppIssue,
  clearAllAppIssues,
} = require('../services/issueService');
const { authenticateAdmin } = require('../middleware/auth');

const router = express.Router();

// ── Client Logging Endpoint ──────────────────────────────────────────────────
router.post('/', async (req, res, next) => {
  try {
    const result = await logAppIssue(req.body);
    return res.status(201).json(result);
  } catch (err) {
    next(err);
  }
});

// ── Admin Protected Endpoints ───────────────────────────────────────────────
router.use(authenticateAdmin);

router.get('/', async (req, res, next) => {
  try {
    const { severity = '', page = '', limit = 50, offset = 0 } = req.query;
    const result = await listAppIssues({ severity, page, limit, offset });
    return res.json(result);
  } catch (err) {
    next(err);
  }
});

router.delete('/clear-all', async (req, res, next) => {
  try {
    await clearAllAppIssues();
    return res.json({ success: true, message: 'تم مسح جميع سجلات الأخطاء.' });
  } catch (err) {
    next(err);
  }
});

router.delete('/:issueId', async (req, res, next) => {
  try {
    await deleteAppIssue(req.params.issueId);
    return res.json({ success: true, message: 'تم حذف السجل بنجاح.' });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
