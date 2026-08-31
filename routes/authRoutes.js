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

// Register Endpoint
router.post('/register', registerLimiter, async (req, res) => {
  try {
    const { username, email, password } = req.body || {};

    if (!username || typeof username !== 'string' || username.trim().length < 3 || username.trim().length > 24) {
      return res.status(400).json({ error: 'INVALID_USERNAME', message: 'اسم المستخدم يجب أن يكون بين 3 و 24 حرفاً.' });
    }

    if (!email || typeof email !== 'string' || !email.includes('@')) {
      return res.status(400).json({ error: 'INVALID_EMAIL', message: 'البريد الإلكتروني غير صحيح.' });
    }

    if (!password || typeof password !== 'string' || password.length < 6) {
      return res.status(400).json({ error: 'WEAK_PASSWORD', message: 'كلمة المرور يجب أن لا تقل عن 6 أحرف.' });
    }

    const cleanUsername = username.trim();
    const cleanEmail = email.trim().toLowerCase();

    const existingEmail = await findUserByEmail(cleanEmail);
    if (existingEmail) {
      return res.status(400).json({ error: 'EMAIL_TAKEN', message: 'البريد الإلكتروني مسجل بالفعل.' });
    }

    const existingUser = await findUserByUsername(cleanUsername);
    if (existingUser) {
      return res.status(400).json({ error: 'USERNAME_TAKEN', message: 'اسم المستخدم مُستخدم بالفعل.' });
    }

    // ALWAYS force role = 'player' for public registration
    const userDTO = await createUser({
      username: cleanUsername,
      email: cleanEmail,
      password,
      role: 'player',
      coins: 0,
    });

    const token = generateToken(userDTO);

    const isProd = process.env.NODE_ENV === 'production';
    res.cookie('auth_token', token, {
      httpOnly: true,
      secure: isProd,
      sameSite: 'lax',
      maxAge: 24 * 60 * 60 * 1000,
    });

    return res.status(201).json({
      success: true,
      token,
      user: userDTO,
    });
  } catch (err) {
    console.error('[Auth API] Register error:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message || 'حدث خطأ غير متوقع أثناء التسجيل.', details: err.stack });
  }
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
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message || 'حدث خطأ أثناء تسجيل الدخول.', details: err.stack });
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
