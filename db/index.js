const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

let pool = null;
let dbConnected = false;

function initPool() {
  const host = process.env.DB_HOST || '127.0.0.1';
  const port = parseInt(process.env.DB_PORT || '3306', 10);
  const user = process.env.DB_USER || 'root';
  const password = process.env.DB_PASSWORD || '';
  const database = process.env.DB_NAME || 'deceit_db';

  if (!process.env.DB_HOST && !process.env.DB_NAME) {
    console.log('[DB] No DB_HOST or DB_NAME configured in environment. Database features will be offline.');
    return null;
  }

  try {
    pool = mysql.createPool({
      host,
      port,
      user,
      password,
      database,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0,
      idleTimeout: 60000,
    });

    console.log(`[DB] MySQL pool initialized (${host}:${port}/${database})`);
    return pool;
  } catch (err) {
    console.error('[DB] Failed to initialize MySQL pool:', err.message);
    pool = null;
    dbConnected = false;
    return null;
  }
}

async function ensureDatabaseExists(host, port, user, password, database) {
  try {
    const tempConn = await mysql.createConnection({ host, port, user, password });
    await tempConn.query(`CREATE DATABASE IF NOT EXISTS \`${database}\`;`);
    await tempConn.end();
  } catch (err) {
    // Suppress if creation fails (database may already exist or user lacks CREATE DB privilege)
  }
}

async function testConnectionAndMigrate() {
  const host = process.env.DB_HOST || '127.0.0.1';
  const port = parseInt(process.env.DB_PORT || '3306', 10);
  const user = process.env.DB_USER || 'root';
  const password = process.env.DB_PASSWORD || '';
  const database = process.env.DB_NAME || 'deceit_db';

  await ensureDatabaseExists(host, port, user, password, database);

  if (!pool) initPool();
  if (!pool) {
    dbConnected = false;
    return false;
  }

  try {
    const conn = await pool.getConnection();
    console.log(`[DB] ✅ Successfully connected to MySQL database "${database}".`);
    conn.release();
    dbConnected = true;

    await runMigrations();
    return true;
  } catch (err) {
    console.warn(`[DB] ⚠️ MySQL connection check failed: ${err.message}. Auth & Admin APIs will return 503.`);
    dbConnected = false;
    return false;
  }
}

async function runMigrations() {
  if (!pool || !dbConnected) return;

  const migrationsDir = path.join(__dirname, 'migrations');
  if (!fs.existsSync(migrationsDir)) return;

  try {
    // Ensure migrations tracking table exists
    await pool.query(`
      CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(255) PRIMARY KEY,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    // Fetch list of already executed migrations
    const [rows] = await pool.query('SELECT name FROM schema_migrations');
    const executedMigrations = new Set(rows.map(r => r.name));

    const files = fs.readdirSync(migrationsDir).filter(f => f.endsWith('.sql')).sort();
    let newlyAppliedCount = 0;

    for (const file of files) {
      if (executedMigrations.has(file)) {
        continue; // Already applied previously, skip execution and output
      }

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
            // Column already exists, record as applied
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
  } catch (err) {
    console.warn(`[DB Migration Error]: ${err.message}`);
  }
}

function getPool() {
  return pool;
}

function isDbConnected() {
  return dbConnected;
}

module.exports = {
  initPool,
  testConnectionAndMigrate,
  getPool,
  isDbConnected,
};
