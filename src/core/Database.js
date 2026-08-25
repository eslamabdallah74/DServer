const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');
const bcrypt = require('bcryptjs');
const config = require('../config/env');

class Database {
  constructor() {
    if (Database.instance) {
      return Database.instance;
    }

    this.pool = null;
    this.isConnected = false;
    Database.instance = this;
  }

  static getInstance() {
    if (!Database.instance) {
      Database.instance = new Database();
    }
    return Database.instance;
  }

  async ensureDatabaseExists() {
    const { host, port, user, password, database } = config.db;
    try {
      const tempConn = await mysql.createConnection({ host, port, user, password });
      await tempConn.query(`CREATE DATABASE IF NOT EXISTS \`${database}\`;`);
      await tempConn.end();
    } catch (err) {
      console.warn('[DB Warning] Database auto-creation check skipped:', err.message);
    }
  }

  async init() {
    await this.ensureDatabaseExists();

    try {
      this.pool = mysql.createPool({
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

      const conn = await this.pool.getConnection();
      console.log(`[DB] ✅ Connected to MySQL database "${config.db.database}" at ${config.db.host}:${config.db.port}`);
      conn.release();
      this.isConnected = true;

      await this.runMigrations();
      await this.seedAdminUser();
      return true;
    } catch (err) {
      console.error(`[DB Error] ❌ Connection failed: ${err.message}`);
      this.isConnected = false;
      return false;
    }
  }

  async runMigrations() {
    if (!this.pool || !this.isConnected) return;

    const migrationsDir = path.join(__dirname, '../db/migrations');
    if (!fs.existsSync(migrationsDir)) return;

    await this.pool.query(`
      CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    const [appliedRows] = await this.pool.query('SELECT filename FROM schema_migrations');
    const appliedSet = new Set(appliedRows.map(r => r.filename));

    const files = fs.readdirSync(migrationsDir).filter(f => f.endsWith('.sql')).sort();
    for (const file of files) {
      if (appliedSet.has(file)) continue;

      const filePath = path.join(migrationsDir, file);
      const sql = fs.readFileSync(filePath, 'utf8');
      if (sql.trim()) {
        try {
          await this.pool.query(sql);
          await this.pool.query('INSERT INTO schema_migrations (filename) VALUES (?)', [file]);
          console.log(`[DB Migration] Applied new migration: ${file}`);
        } catch (err) {
          if (!err.message.includes('already exists') && !err.message.includes('Duplicate column')) {
            console.warn(`[DB Migration Warning] ${file}: ${err.message}`);
          }
        }
      }
    }
  }

  async seedAdminUser() {
    if (!this.pool || !this.isConnected) return;

    const { username, email, password } = config.adminSeed;
    if (!username || !email || !password) return;

    try {
      const [rows] = await this.pool.query('SELECT COUNT(*) as count FROM admins');
      if (rows[0].count === 0) {
        const passwordHash = await bcrypt.hash(password, 10);
        await this.pool.query(
          'INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)',
          [username.trim(), email.trim().toLowerCase(), passwordHash]
        );
        console.log(`[DB Seed] 👑 Initial Admin user created: "${username}" (${email}) from .env credentials`);
      }
    } catch (err) {
      console.error('[DB Seed Error] Failed to seed admin user:', err.message);
    }
  }

  getPool() {
    if (!this.pool) {
      throw new Error('Database connection pool is not initialized.');
    }
    return this.pool;
  }
}

module.exports = Database;
