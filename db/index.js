const mysql = require('mysql2/promise');
const { Pool: PgPool } = require('pg');
const fs = require('fs');
const path = require('path');

let pool = null;
let dbType = null; // 'pg' or 'mysql'
let dbConnected = false;
let isMigrating = false;

function initPool() {
  // Priority 1: Postgres via DATABASE_URL (Vercel/Neon)
  const pgUrl = process.env.POSTGRES_URL || process.env.DATABASE_URL;
  if (pgUrl && pgUrl.startsWith('postgres')) {
    try {
      pool = new PgPool({ connectionString: pgUrl, ssl: { rejectUnauthorized: false } });
      dbType = 'pg';
      console.log('[DB] PostgreSQL pool initialized from DATABASE_URL');
      return pool;
    } catch (err) {
      console.error('[DB] Failed to init PostgreSQL pool:', err.message);
    }
  }

  // Priority 2: MySQL via env vars
  const host = process.env.DB_HOST || '127.0.0.1';
  const port = parseInt(process.env.DB_PORT || '3306', 10);
  const user = process.env.DB_USER || 'root';
  const password = process.env.DB_PASSWORD || '';
  const database = process.env.DB_NAME || 'deceit_db';

  if (!process.env.DB_HOST && !process.env.DB_NAME) {
    console.log('[DB] No database configured. Running in-memory mode.');
    return null;
  }

  try {
    pool = mysql.createPool({
      host, port, user, password, database,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0,
      connectTimeout: 5000,
      idleTimeout: 60000,
    });
    dbType = 'mysql';
    console.log(`[DB] MySQL pool initialized (${host}:${port}/${database})`);
    return pool;
  } catch (err) {
    console.error('[DB] Failed to init MySQL pool:', err.message);
    pool = null;
    dbConnected = false;
    return null;
  }
}

// Unified query helper — same interface for both MySQL and Postgres
// Returns [rows, result] regardless of driver
async function query(sql, params = []) {
  if (!pool) initPool();
  if (!pool) throw new Error('No database pool');

  const currentDbType = getDbType();

  if (currentDbType === 'pg') {
    // Convert MySQL ? placeholders to Postgres $1, $2, ...
    let idx = 0;
    const pgSql = sql.replace(/\?/g, () => `$${++idx}`);
    const finalSql = pgSql.replace(/\bNOW\(\)/gi, 'NOW()');
    try {
      const result = await pool.query(finalSql, params);
      return [result.rows, result];
    } catch (err) {
      console.error('[DB PG Query Error]', err.message, 'SQL:', finalSql, 'Params:', params);
      throw new Error(`Postgres Error: ${err.message} | Executed SQL: "${finalSql}" | Params: ${JSON.stringify(params)}`);
    }
  } else {
    try {
      return await pool.query(sql, params);
    } catch (err) {
      console.error('[DB MySQL Query Error]', err.message, 'SQL:', sql, 'Params:', params);
      throw new Error(`MySQL Error: ${err.message} | Executed SQL: "${sql}" | Params: ${JSON.stringify(params)}`);
    }
  }
}

async function testConnectionAndMigrate() {
  if (isMigrating) return isDbConnected();
  isMigrating = true;

  try {
    if (!pool) initPool();
    if (!pool) {
      dbConnected = false;
      isMigrating = false;
      return false;
    }

    if (getDbType() === 'pg') {
      const client = await pool.connect();
      console.log('[DB] ✅ Connected to PostgreSQL.');
      client.release();
      dbConnected = true;
      await runPgMigrations();
    } else {
      const host = process.env.DB_HOST || '127.0.0.1';
      const port = parseInt(process.env.DB_PORT || '3306', 10);
      const user = process.env.DB_USER || 'root';
      const password = process.env.DB_PASSWORD || '';
      const database = process.env.DB_NAME || 'deceit_db';
      await ensureDatabaseExists(host, port, user, password, database);

      const conn = await pool.getConnection();
      console.log(`[DB] ✅ Connected to MySQL "${database}".`);
      conn.release();
      dbConnected = true;
      await runMysqlMigrations();
    }
  } catch (err) {
    console.warn(`[DB] ⚠️ Connection failed: ${err.message}. Running in-memory mode.`);
    dbConnected = false;
  } finally {
    isMigrating = false;
  }

  return isDbConnected();
}

async function ensureDatabaseExists(host, port, user, password, database) {
  try {
    const tempConn = await mysql.createConnection({ host, port, user, password, connectTimeout: 4000 });
    await tempConn.query(`CREATE DATABASE IF NOT EXISTS \`${database}\`;`);
    await tempConn.end();
  } catch (err) {
    // User might not have CREATE privilege — that's fine
  }
}

async function runPgMigrations() {
  if (!pool || !dbConnected) return;
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'player',
        coins INT NOT NULL DEFAULT 0,
        is_banned INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
      );
    `);
    await pool.query(`
      CREATE TABLE IF NOT EXISTS admin_audit_logs (
        id SERIAL PRIMARY KEY,
        admin_user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        admin_email VARCHAR(100) NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        target_type VARCHAR(50) NOT NULL,
        target_id VARCHAR(100) NOT NULL,
        details JSONB DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT NOW()
      );
    `);
    await pool.query(`
      CREATE TABLE IF NOT EXISTS user_feedback (
        id SERIAL PRIMARY KEY,
        feedback_id VARCHAR(64) UNIQUE NOT NULL,
        rating INT NOT NULL DEFAULT 5,
        comment TEXT,
        category VARCHAR(50),
        contact_email VARCHAR(100),
        created_at TIMESTAMP DEFAULT NOW()
      );
    `);
    await pool.query(`
      CREATE TABLE IF NOT EXISTS match_logs (
        id SERIAL PRIMARY KEY,
        match_id VARCHAR(64) UNIQUE NOT NULL,
        player_count INT NOT NULL,
        winner_team VARCHAR(50) NOT NULL,
        roles_used JSONB,
        duration_seconds INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT NOW()
      );
    `);
    await pool.query(`
      CREATE TABLE IF NOT EXISTS player_profiles (
        id SERIAL PRIMARY KEY,
        player_id VARCHAR(64) NOT NULL UNIQUE,
        nickname VARCHAR(255),
        name VARCHAR(100),
        age INT,
        gender VARCHAR(20),
        phone_number VARCHAR(50),
        coins INT DEFAULT 0,
        points INT DEFAULT 1,
        matches_played INT DEFAULT 0,
        wins INT DEFAULT 0,
        owned_roles_json TEXT,
        stats_wins INT DEFAULT 0,
        stats_losses INT DEFAULT 0,
        stats_matches INT DEFAULT 0,
        device_brand VARCHAR(255),
        device_model VARCHAR(255),
        device_os VARCHAR(255),
        device_os_version TEXT,
        app_version VARCHAR(255),
        last_sync_timestamp BIGINT DEFAULT 0,
        last_seen_at TIMESTAMP DEFAULT NOW(),
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
      );
    `);
    await pool.query(`
      CREATE TABLE IF NOT EXISTS active_rooms (
        room_code VARCHAR(10) PRIMARY KEY,
        room_data JSONB NOT NULL,
        last_activity_at TIMESTAMP DEFAULT NOW(),
        created_at TIMESTAMP DEFAULT NOW()
      );
    `);
    await pool.query(`
      CREATE TABLE IF NOT EXISTS app_issues (
        id SERIAL PRIMARY KEY,
        issue_id VARCHAR(64) UNIQUE,
        severity VARCHAR(50) DEFAULT 'error',
        title VARCHAR(255),
        description TEXT,
        message TEXT,
        stack_trace TEXT,
        page VARCHAR(255),
        app_version VARCHAR(50),
        device_info TEXT,
        status VARCHAR(20) DEFAULT 'open',
        created_at TIMESTAMP DEFAULT NOW()
      );
    `);
    console.log('[DB] PostgreSQL tables ready (users, admin_audit_logs, user_feedback, match_logs, player_profiles, app_issues).');
  } catch (err) {
    console.warn('[DB PG Migration]', err.message);
  }
}

async function runMysqlMigrations() {
  if (!pool || !dbConnected) return;

  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS active_rooms (
        room_code VARCHAR(10) PRIMARY KEY,
        room_data LONGTEXT NOT NULL,
        last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);
  } catch (err) {
    console.warn('[DB MySQL ActiveRooms]', err.message);
  }

  const migrationsDir = path.join(__dirname, 'migrations');
  if (!fs.existsSync(migrationsDir)) return;

  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(255) PRIMARY KEY,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    const [rows] = await pool.query('SELECT name FROM schema_migrations');
    const executedMigrations = new Set(rows.map(r => r.name));

    const files = fs.readdirSync(migrationsDir).filter(f => f.endsWith('.sql')).sort();
    let newlyAppliedCount = 0;

    for (const file of files) {
      if (executedMigrations.has(file)) continue;

      const filePath = path.join(migrationsDir, file);
      const sql = fs.readFileSync(filePath, 'utf8');

      if (sql.trim()) {
        try {
          await pool.query(sql);
          await pool.query('INSERT INTO schema_migrations (name) VALUES (?)', [file]);
          console.log(`[DB Migration] Applied ${file}`);
          newlyAppliedCount++;
        } catch (err) {
          if (err.code === 'ER_DUP_FIELDNAME' || err.message.includes('Duplicate column')) {
            await pool.query('INSERT IGNORE INTO schema_migrations (name) VALUES (?)', [file]);
          } else {
            console.warn(`[DB Migration Warning] ${file}: ${err.message}`);
          }
        }
      }
    }

    if (newlyAppliedCount === 0) {
      console.log('[DB] Database schema is up to date.');
    }
    await seedDefaultAdminUser();
  } catch (err) {
    console.warn(`[DB Migration Error]: ${err.message}`);
  }
}

async function seedDefaultAdminUser() {
  const adminUsername = process.env.ADMIN_USERNAME || 'eslam@deceit74';
  const adminEmail = process.env.ADMIN_EMAIL || 'eslam@deceit';
  const adminPassword = process.env.ADMIN_PASSWORD || 'deceit2026';

  if (!adminUsername || !adminPassword) return;

  try {
    const authService = require('../auth/service');
    const existing = await authService.findUserByUsername(adminUsername);
    if (!existing) {
      await authService.createUser({
        username: adminUsername,
        email: adminEmail,
        password: adminPassword,
        role: 'admin',
        coins: 1000,
      });
      console.log(`[DB Seed] ✅ Seeded Admin User "${adminUsername}" (${adminEmail})`);
    }
  } catch (err) {
    console.warn('[DB Seed Notice]:', err.message);
  }
}

function getPool() {
  return pool;
}

function getDbType() {
  return dbType;
}

function isDbConnected() {
  return dbConnected;
}

module.exports = {
  initPool,
  testConnectionAndMigrate,
  query,
  getPool,
  getDbType,
  isDbConnected,
};
