const mysql = require('mysql2/promise');
const { Pool: PgPool } = require('pg');
const fs = require('fs');
const path = require('path');

let pool = null;
let dbType = 'mysql'; // 'mysql' | 'pg'
let dbConnected = false;
let isMigrating = false;

function initPool() {
  const pgUrl = process.env.POSTGRES_URL || process.env.VERCEL_POSTGRES_URL;
  const mysqlUrl = process.env.MYSQL_URL || process.env.DATABASE_URL;

  if (pgUrl) {
    try {
      pool = new PgPool({
        connectionString: pgUrl,
        ssl: { rejectUnauthorized: false },
      });
      dbType = 'pg';
      console.log('[DB] PostgreSQL pool initialized from POSTGRES_URL (Vercel Postgres / Neon)');
      return pool;
    } catch (err) {
      console.error('[DB] Failed to initialize PostgreSQL pool:', err.message);
    }
  }

  if (mysqlUrl) {
    try {
      pool = mysql.createPool(mysqlUrl);
      dbType = 'mysql';
      console.log('[DB] MySQL pool initialized from MYSQL_URL / DATABASE_URL');
      return pool;
    } catch (err) {
      console.error('[DB] Failed to initialize MySQL pool:', err.message);
    }
  }

  const host = process.env.DB_HOST || '127.0.0.1';
  const port = parseInt(process.env.DB_PORT || '3306', 10);
  const user = process.env.DB_USER || 'root';
  const password = process.env.DB_PASSWORD || '';
  const database = process.env.DB_NAME || 'deceit_db';

  if (!process.env.DB_HOST && !process.env.DB_NAME && !pgUrl && !mysqlUrl) {
    console.log('[DB] No DB configured in environment. Running in-memory mode.');
    return null;
  }

  try {
    const config = {
      host,
      port,
      user,
      password,
      database,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0,
      connectTimeout: 5000,
      idleTimeout: 60000,
    };

    if (process.env.DB_SSL === 'true' || process.env.VERCEL) {
      config.ssl = { rejectUnauthorized: false };
    }

    pool = mysql.createPool(config);
    dbType = 'mysql';
    console.log(`[DB] MySQL pool initialized (${host}:${port}/${database})`);
    return pool;
  } catch (err) {
    console.error('[DB] Failed to initialize MySQL pool:', err.message);
    pool = null;
    dbConnected = false;
    return null;
  }
}

async function testConnectionAndMigrate() {
  if (isMigrating) return dbConnected;
  isMigrating = true;

  try {
    if (!pool) initPool();
    if (!pool) {
      dbConnected = false;
      isMigrating = false;
      return false;
    }

    if (dbType === 'pg') {
      const client = await pool.connect();
      console.log('[DB] ✅ Successfully connected to PostgreSQL database (Vercel Postgres / Neon).');
      client.release();
      dbConnected = true;
      await runPgMigrations();
    } else {
      const conn = await pool.getConnection();
      console.log(`[DB] ✅ Successfully connected to MySQL database.`);
      conn.release();
      dbConnected = true;
      await runMysqlMigrations();
    }
  } catch (err) {
    console.warn(`[DB] ⚠️ Connection check failed: ${err.message}. Running in-memory fallback.`);
    dbConnected = false;
  } finally {
    isMigrating = false;
  }

  return dbConnected;
}

async function runPgMigrations() {
  if (!pool || !dbConnected || dbType !== 'pg') return;
  try {
    await pool.query(`
      CREATE TABLE IF NOT EXISTS player_profiles (
        player_id VARCHAR(255) PRIMARY KEY,
        nickname VARCHAR(255) NOT NULL,
        coins INT DEFAULT 0,
        owned_roles_json TEXT,
        stats_wins INT DEFAULT 0,
        stats_losses INT DEFAULT 0,
        stats_matches INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );

      CREATE TABLE IF NOT EXISTS app_issues (
        id SERIAL PRIMARY KEY,
        issue_type VARCHAR(100),
        message TEXT,
        stack_trace TEXT,
        device_info JSONB,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);
    console.log('[DB] PostgreSQL schema tables (player_profiles & app_issues) ready.');
  } catch (err) {
    console.warn('[DB Migration Warning (PG)]', err.message);
  }
}

async function runMysqlMigrations() {
  if (!pool || !dbConnected || dbType !== 'mysql') return;

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
      console.log('[DB] MySQL schema is up to date.');
    }
  } catch (err) {
    console.warn(`[DB Migration Error]: ${err.message}`);
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
  getPool,
  getDbType,
  isDbConnected,
};
