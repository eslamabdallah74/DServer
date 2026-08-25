const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const config = require('../../config/env');

class AuthService {
  constructor(authRepository) {
    this.authRepository = authRepository;
  }

  async login(usernameOrEmail, password) {
    const admin = await this.authRepository.findByUsernameOrEmail(usernameOrEmail);
    if (!admin) {
      return { success: false, message: 'اسم المستخدم أو كلمة المرور غير صحيحة.' };
    }

    const isMatch = await bcrypt.compare(password, admin.password_hash);
    if (!isMatch) {
      return { success: false, message: 'اسم المستخدم أو كلمة المرور غير صحيحة.' };
    }

    const tokenPayload = {
      id: admin.id,
      username: admin.username,
      email: admin.email,
    };

    const token = jwt.sign(tokenPayload, config.jwt.secret, {
      expiresIn: config.jwt.expiresIn,
    });

    return {
      success: true,
      token,
      admin: {
        id: admin.id,
        username: admin.username,
        email: admin.email,
      },
    };
  }

  async getAdminProfile(adminId) {
    return this.authRepository.findById(adminId);
  }
}

module.exports = AuthService;
