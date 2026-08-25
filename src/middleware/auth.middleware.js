const jwt = require('jsonwebtoken');
const config = require('../config/env');
const ResponseHandler = require('../core/ResponseHandler');

class AuthMiddleware {
  static authenticateAdmin(req, res, next) {
    let token = null;

    const authHeader = req.headers['authorization'];
    if (authHeader && authHeader.startsWith('Bearer ')) {
      token = authHeader.substring(7);
    }

    if (!token && req.cookies && req.cookies.admin_token) {
      token = req.cookies.admin_token;
    }

    if (!token) {
      return ResponseHandler.unauthorized(res);
    }

    try {
      const decoded = jwt.verify(token, config.jwt.secret);
      req.admin = decoded;
      next();
    } catch (err) {
      return ResponseHandler.forbidden(res);
    }
  }
}

module.exports = AuthMiddleware;
