const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');
const bcrypt = require('bcryptjs');
const config = require('../config/env');

let pool = null;
let isConnected = false;

async function ensureDatabaseExists() {
  const { host, port, user, password, database } = config.db;
  try {
    const tempConn = await mysql.createConnection({ host, port, user, password });
    await tempConn.query(`CREATE DATABASE IF NOT EXISTS \`${database}\`;`);
    await tempConn.end();
  } catch (err) {
    console.warn('[DB Warning] Database auto-creation check skipped:', err.message);
  }
}

async function initDatabase() {
  await ensureDatabaseExists();

  try {
    pool = mysql.createPool({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      database: config.db.database,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0,
      idleTimeout: 60000,
    });

    const conn = await pool.getConnection();
    console.log(`[DB] ✅ Connected to MySQL database "${config.db.database}" at ${config.db.host}:${config.db.port}`);
    conn.release();
    isConnected = true;

    await runMigrations();
    await seedAdminUser();
    return true;
  } catch (err) {
    console.error(`[DB Error] ❌ Connection failed: ${err.message}`);
    isConnected = false;
    return false;
  }
}

async function runMigrations() {
  if (!pool || !isConnected) return;

  const migrationsDir = path.join(__dirname, 'migrations');
  if (!fs.existsSync(migrationsDir)) return;

  // Create schema_migrations tracking table if it doesn't exist
  await pool.query(`
    CREATE TABLE IF NOT EXISTS schema_migrations (
      filename VARCHAR(255) PRIMARY KEY,
      applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
  `);

  const [appliedRows] = await pool.query('SELECT filename FROM schema_migrations');
  const appliedSet = new Set(appliedRows.map(r => r.filename));

  const files = fs.readdirSync(migrationsDir).filter(f => f.endsWith('.sql')).sort();
  for (const file of files) {
    if (appliedSet.has(file)) {
      continue; // Migration already applied — skip silently
    }

    const filePath = path.join(migrationsDir, file);
    const sql = fs.readFileSync(filePath, 'utf8');
    if (sql.trim()) {
      try {
        await pool.query(sql);
        await pool.query('INSERT INTO schema_migrations (filename) VALUES (?)', [file]);
        console.log(`[DB Migration] Applied new migration: ${file}`);
      } catch (err) {
        if (!err.message.includes('already exists') && !err.message.includes('Duplicate column')) {
          console.warn(`[DB Migration Warning] ${file}: ${err.message}`);
        }
      }
    }
  }
}

async function seedAdminUser() {
  if (!pool || !isConnected) return;

  const { username, email, password } = config.adminSeed;
  if (!username || !email || !password) return;

  try {
    const [rows] = await pool.query('SELECT COUNT(*) as count FROM admins');
    if (rows[0].count === 0) {
      const passwordHash = await bcrypt.hash(password, 10);
      await pool.query(
        'INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)',
        [username.trim(), email.trim().toLowerCase(), passwordHash]
      );
      console.log(`[DB Seed] 👑 Initial Admin user created: "${username}" (${email}) from .env credentials`);
    }
  } catch (err) {
    console.error('[DB Seed Error] Failed to seed admin user:', err.message);
  }
}

function getPool() {
  return pool;
}

function isDbConnected() {
  return isConnected;
}

module.exports = {
  initDatabase,
  getPool,
  isDbConnected,
};
