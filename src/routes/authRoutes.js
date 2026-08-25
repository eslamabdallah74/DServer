const express = require('express');
const { loginAdmin, getAdminById } = require('../services/authService');
const { authenticateAdmin } = require('../middleware/auth');

const router = express.Router();

// POST /api/auth/login
router.post('/login', async (req, res, next) => {
  try {
    const { username, password } = req.body || {};
    if (!username || !password) {
      return res.status(400).json({
        error: 'INVALID_INPUT',
        message: 'اسم المستخدم وكلمة المرور مطلوبة.',
      });
    }

    const result = await loginAdmin(username, password);
    if (!result.success) {
      return res.status(401).json(result);
    }

    // Set cookie for browser dashboard convenience
    res.cookie('admin_token', result.token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      maxAge: 7 * 24 * 60 * 60 * 1000,
    });

    return res.json(result);
  } catch (err) {
    next(err);
  }
});

// GET /api/auth/me
router.get('/me', authenticateAdmin, async (req, res, next) => {
  try {
    const admin = await getAdminById(req.admin.id);
    if (!admin) {
      return res.status(404).json({ error: 'ADMIN_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }
    return res.json({ admin });
  } catch (err) {
    next(err);
  }
});

// POST /api/auth/logout
router.post('/logout', (req, res) => {
  res.clearCookie('admin_token');
  return res.json({ success: true, message: 'تم تسجيل الخروج بنجاح.' });
});

module.exports = router;
