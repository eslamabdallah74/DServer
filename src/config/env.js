require('dotenv').config();

module.exports = {
  env: process.env.NODE_ENV || 'production',
  port: process.env.PORT || 3001,
  jwtSecret: process.env.JWT_SECRET || 'deceit_offline_super_secret_jwt_key_2026_998877665544332211',
  db: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: parseInt(process.env.DB_PORT || '3306', 10),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    name: process.env.DB_NAME || 'deceit_db',
    connectionLimit: 10,
    connectTimeout: 5000,
  }
};
