const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { getPool, isDbConnected } = require('../db');

const BCRYPT_SALT_ROUNDS = 10;
const JWT_EXPIRES_IN = '24h';

function getJwtSecret() {
  const secret = process.env.JWT_SECRET;
  if (!secret) {
    // Development fallback with clear warning log
    return 'DEV_ONLY_INSECURE_SECRET_CHANGE_IN_ENV';
  }
  return secret;
}

async function hashPassword(plainPassword) {
  return await bcrypt.hash(plainPassword, BCRYPT_SALT_ROUNDS);
}

async function comparePassword(plainPassword, passwordHash) {
  return await bcrypt.compare(plainPassword, passwordHash);
}

function generateToken(userDTO) {
  const secret = getJwtSecret();
  return jwt.sign(
    {
      id: userDTO.id,
      username: userDTO.username,
      email: userDTO.email,
      role: userDTO.role,
    },
    secret,
    { expiresIn: JWT_EXPIRES_IN }
  );
}

function verifyToken(token) {
  try {
    const secret = getJwtSecret();
    return jwt.verify(token, secret);
  } catch (err) {
    return null;
  }
}

function sanitizeUserDTO(userRow) {
  if (!userRow) return null;
  return {
    id: userRow.id,
    username: userRow.username,
    email: userRow.email,
    role: userRow.role,
    coins: userRow.coins || 0,
    isBanned: Boolean(userRow.is_banned),
    createdAt: userRow.created_at,
    updatedAt: userRow.updated_at,
  };
}

async function findUserByEmail(email) {
  if (!isDbConnected()) return null;
  const pool = getPool();
  const [rows] = await pool.query('SELECT * FROM users WHERE email = ? LIMIT 1', [email]);
  return rows.length > 0 ? rows[0] : null;
}

async function findUserByUsername(username) {
  if (!isDbConnected()) return null;
  const pool = getPool();
  const [rows] = await pool.query('SELECT * FROM users WHERE username = ? LIMIT 1', [username]);
  return rows.length > 0 ? rows[0] : null;
}

async function findUserById(id) {
  if (!isDbConnected()) return null;
  const pool = getPool();
  const [rows] = await pool.query('SELECT * FROM users WHERE id = ? LIMIT 1', [id]);
  return rows.length > 0 ? rows[0] : null;
}

async function createUser({ username, email, password, role = 'player', coins = 0 }) {
  if (!isDbConnected()) throw new Error('DB_OFFLINE');
  const pool = getPool();
  const passwordHash = await hashPassword(password);
  // ALWAYS force player role if not admin creation
  const finalRole = role === 'admin' ? 'admin' : 'player';

  const [result] = await pool.query(
    'INSERT INTO users (username, email, password_hash, role, coins) VALUES (?, ?, ?, ?, ?)',
    [username, email, passwordHash, finalRole, coins]
  );

  const newId = result.insertId;
  const user = await findUserById(newId);
  return sanitizeUserDTO(user);
}

async function listUsers({ search = '', limit = 50, offset = 0 } = {}) {
  if (!isDbConnected()) return { users: [], total: 0 };
  const pool = getPool();

  let sql = 'SELECT * FROM users';
  const params = [];

  if (search.trim()) {
    sql += ' WHERE username LIKE ? OR email LIKE ?';
    const term = `%${search.trim()}%`;
    params.push(term, term);
  }

  const [countRows] = await pool.query(`SELECT COUNT(*) as total FROM (${sql}) as sub`, params);
  const total = countRows[0].total;

  sql += ' ORDER BY id DESC LIMIT ? OFFSET ?';
  params.push(parseInt(limit, 10), parseInt(offset, 10));

  const [rows] = await pool.query(sql, params);
  return {
    users: rows.map(sanitizeUserDTO),
    total,
  };
}

async function countAdmins() {
  if (!isDbConnected()) return 0;
  const pool = getPool();
  const [rows] = await pool.query('SELECT COUNT(*) as total FROM users WHERE role = ?', ['admin']);
  return rows[0].total;
}

async function updateUserRole(userId, newRole) {
  if (!isDbConnected()) throw new Error('DB_OFFLINE');
  const pool = getPool();
  await pool.query('UPDATE users SET role = ? WHERE id = ?', [newRole, userId]);
  const user = await findUserById(userId);
  return sanitizeUserDTO(user);
}

async function updateUserCoins(userId, newCoins) {
  if (!isDbConnected()) throw new Error('DB_OFFLINE');
  const pool = getPool();
  await pool.query('UPDATE users SET coins = ? WHERE id = ?', [newCoins, userId]);
  const user = await findUserById(userId);
  return sanitizeUserDTO(user);
}

async function deleteUser(userId) {
  if (!isDbConnected()) throw new Error('DB_OFFLINE');
  const pool = getPool();
  const [res] = await pool.query('DELETE FROM users WHERE id = ?', [userId]);
  return res.affectedRows > 0;
}

async function toggleUserBan(userId, isBanned) {
  if (!isDbConnected()) throw new Error('DB_OFFLINE');
  const pool = getPool();
  await pool.query('UPDATE users SET is_banned = ? WHERE id = ?', [isBanned ? 1 : 0, userId]);
  const user = await findUserById(userId);
  return sanitizeUserDTO(user);
}

async function resetUserPassword(userId, newPassword) {
  if (!isDbConnected()) throw new Error('DB_OFFLINE');
  const pool = getPool();
  const passwordHash = await hashPassword(newPassword);
  await pool.query('UPDATE users SET password_hash = ? WHERE id = ?', [passwordHash, userId]);
  const user = await findUserById(userId);
  return sanitizeUserDTO(user);
}

module.exports = {
  hashPassword,
  comparePassword,
  generateToken,
  verifyToken,
  sanitizeUserDTO,
  findUserByEmail,
  findUserByUsername,
  findUserById,
  createUser,
  listUsers,
  countAdmins,
  updateUserRole,
  updateUserCoins,
  toggleUserBan,
  resetUserPassword,
  deleteUser,
};
