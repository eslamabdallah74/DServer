const jwt = require('jsonwebtoken');
const config = require('../config/env');

function authenticateAdmin(req, res, next) {
  let token = null;

  // 1. Check Authorization Header
  const authHeader = req.headers['authorization'];
  if (authHeader && authHeader.startsWith('Bearer ')) {
    token = authHeader.substring(7);
  }

  // 2. Check Cookie
  if (!token && req.cookies && req.cookies.admin_token) {
    token = req.cookies.admin_token;
  }

  if (!token) {
    return res.status(401).json({
      error: 'UNAUTHORIZED',
      message: 'الرجاء تسجيل الدخول أولاً للوصول إلى لوحة التحكم.',
    });
  }

  try {
    const decoded = jwt.verify(token, config.jwt.secret);
    req.admin = decoded;
    next();
  } catch (err) {
    return res.status(403).json({
      error: 'INVALID_TOKEN',
      message: 'جلسة الدخول غير صالحة أو انتهت مدتها.',
    });
  }
}

module.exports = {
  authenticateAdmin,
};
