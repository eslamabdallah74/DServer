const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { getPool } = require('../db/connection');
const config = require('../config/env');

async function loginAdmin(usernameOrEmail, password) {
  const pool = getPool();
  if (!pool) {
    throw new Error('Database is offline');
  }

  const query = `
    SELECT * FROM admins 
    WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
    LIMIT 1
  `;
  const [rows] = await pool.query(query, [usernameOrEmail.trim(), usernameOrEmail.trim()]);

  if (rows.length === 0) {
    return { success: false, message: 'اسم المستخدم أو كلمة المرور غير صحيحة.' };
  }

  const admin = rows[0];
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

async function getAdminById(adminId) {
  const pool = getPool();
  if (!pool) return null;

  const [rows] = await pool.query(
    'SELECT id, username, email, created_at FROM admins WHERE id = ?',
    [adminId]
  );
  return rows[0] || null;
}

module.exports = {
  loginAdmin,
  getAdminById,
};
