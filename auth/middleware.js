const { verifyToken } = require('./service');
const { isDbConnected } = require('../db');

function dbCheck(req, res, next) {
  if (!isDbConnected()) {
    return res.status(503).json({
      error: 'DATABASE_UNAVAILABLE',
      message: 'خدمة وقواعد بيانات المستخدمين غير متاحة حالياً. يرجى المحاولة لاحقاً.',
    });
  }
  next();
}

function authenticateToken(req, res, next) {
  let token = null;

  // 1. Check HttpOnly cookie
  if (req.cookies && req.cookies.auth_token) {
    token = req.cookies.auth_token;
  }

  // 2. Check Authorization header
  if (!token && req.headers.authorization) {
    const parts = req.headers.authorization.split(' ');
    if (parts.length === 2 && parts[0] === 'Bearer') {
      token = parts[1];
    }
  }

  if (!token) {
    return res.status(401).json({
      error: 'UNAUTHORIZED',
      message: 'يلزم تسجيل الدخول للوصول إلى هذه الخدمة.',
    });
  }

  const payload = verifyToken(token);
  if (!payload) {
    return res.status(401).json({
      error: 'INVALID_TOKEN',
      message: 'انتهت صلاحية الجلسة أو الرمز غير صالح.',
    });
  }

  req.user = payload;
  next();
}

function requireAdmin(req, res, next) {
  if (!req.user || req.user.role !== 'admin') {
    return res.status(403).json({
      error: 'FORBIDDEN',
      message: 'هذه الخدمة مخصصة للمشرفين والمسؤولين فقط.',
    });
  }
  next();
}

function validateOrigin(req, res, next) {
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
    return next();
  }

  const origin = req.headers.origin || req.headers.referer;
  const host = req.headers.host;

  // If origin/referer present, ensure host matches
  if (origin && host) {
    try {
      const url = new URL(origin);
      if (url.host !== host && !url.host.includes(host)) {
        return res.status(403).json({
          error: 'CSRF_ORIGIN_MISMATCH',
          message: 'طلب غير مصرح به عبر نطاقات خارجية.',
        });
      }
    } catch (e) {}
  }
  next();
}

module.exports = {
  dbCheck,
  authenticateToken,
  requireAdmin,
  validateOrigin,
};
