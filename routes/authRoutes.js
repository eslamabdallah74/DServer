const express = require('express');
const rateLimit = require('express-rate-limit');
const {
  createUser,
  findUserByEmail,
  findUserByUsername,
  findUserById,
  comparePassword,
  generateToken,
  sanitizeUserDTO,
} = require('../auth/service');
const { dbCheck, authenticateToken, validateOrigin } = require('../auth/middleware');

const router = express.Router();

const registerLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 10, // max 10 registrations per hour per IP
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'RATE_LIMIT_EXCEEDED', message: 'تجاوزت الحد المسموح به لإنشاء الحسابات. حاول مجدداً بعد ساعة.' },
});

const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 10, // max 10 attempts per 15m per IP
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'RATE_LIMIT_EXCEEDED', message: 'تجاوزت محاولات تسجيل الدخول المسموح بها. حاول مجدداً بعد 15 دقيقة.' },
});

// All auth routes check DB connection
router.use(dbCheck);
router.use(validateOrigin);

// Register Endpoint (Disabled - Public registration is disabled)
router.post('/register', (req, res) => {
  return res.status(403).json({ error: 'REGISTRATION_DISABLED', message: 'التسجيل العام معطل حالياً.' });
});

// Login Endpoint
router.post('/login', loginLimiter, async (req, res) => {
  try {
    const { login, password } = req.body || {};

    if (!login || !password) {
      return res.status(400).json({ error: 'INVALID_CREDENTIALS', message: 'يرجى إدخال اسم المستخدم/البريد وكلمة المرور.' });
    }

    const cleanLogin = login.trim();
    let userRow = null;

    if (cleanLogin.includes('@')) {
      userRow = await findUserByEmail(cleanLogin.toLowerCase());
    } else {
      userRow = await findUserByUsername(cleanLogin);
    }

    if (!userRow) {
      return res.status(401).json({ error: 'INVALID_CREDENTIALS', message: 'بيانات الدخول غير صحيحة.' });
    }

    if (userRow.is_banned) {
      return res.status(403).json({ error: 'ACCOUNT_BANNED', message: 'تم حظر هذا الحساب من قبل إدارة اللعبة.' });
    }

    const isMatch = await comparePassword(password, userRow.password_hash);
    if (!isMatch) {
      return res.status(401).json({ error: 'INVALID_CREDENTIALS', message: 'بيانات الدخول غير صحيحة.' });
    }

    const userDTO = sanitizeUserDTO(userRow);
    const token = generateToken(userDTO);

    const isProd = process.env.NODE_ENV === 'production';
    res.cookie('auth_token', token, {
      httpOnly: true,
      secure: isProd,
      sameSite: 'lax',
      maxAge: 24 * 60 * 60 * 1000,
    });

    return res.json({
      success: true,
      token,
      user: userDTO,
    });
  } catch (err) {
    console.error('[Auth API] Login error:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: 'حدث خطأ أثناء تسجيل الدخول.' });
  }
});

// Logout Endpoint
router.post('/logout', (req, res) => {
  res.clearCookie('auth_token');
  return res.json({ success: true, message: 'تم تسجيل الخروج بنجاح.' });
});

// Me Endpoint
router.get('/me', authenticateToken, async (req, res) => {
  try {
    const userRow = await findUserById(req.user.id);
    if (!userRow) {
      return res.status(404).json({ error: 'USER_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }
    return res.json({ user: sanitizeUserDTO(userRow) });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: 'تعذر جلب معلومات الحساب.' });
  }
});

module.exports = router;
