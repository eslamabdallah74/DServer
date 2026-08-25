const ResponseHandler = require('../../core/ResponseHandler');

class AuthController {
  constructor(authService) {
    this.authService = authService;
  }

  async login(req, res, next) {
    try {
      const { username, password } = req.body || {};
      if (!username || !password) {
        return ResponseHandler.badRequest(res, 'اسم المستخدم وكلمة المرور مطلوبة.');
      }

      const result = await this.authService.login(username, password);
      if (!result.success) {
        return ResponseHandler.unauthorized(res, result.message, 'INVALID_CREDENTIALS');
      }

      res.cookie('admin_token', result.token, {
        httpOnly: true,
        secure: process.env.NODE_ENV === 'production',
        maxAge: 7 * 24 * 60 * 60 * 1000,
      });

      return ResponseHandler.success(res, result);
    } catch (err) {
      next(err);
    }
  }

  async getMe(req, res, next) {
    try {
      const admin = await this.authService.getAdminProfile(req.admin.id);
      if (!admin) {
        return ResponseHandler.notFound(res, 'المستخدم غير موجود.', 'ADMIN_NOT_FOUND');
      }
      return ResponseHandler.success(res, { admin });
    } catch (err) {
      next(err);
    }
  }

  logout(req, res) {
    res.clearCookie('admin_token');
    return ResponseHandler.success(res, { success: true, message: 'تم تسجيل الخروج بنجاح.' });
  }
}

module.exports = AuthController;
