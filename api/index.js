const { testConnectionAndMigrate, isDbConnected, getDbType } = require('../db');

let dbReady = null;

async function ensureDb() {
  if (!dbReady) {
    const hasPgUrl = Boolean(process.env.POSTGRES_URL || process.env.DATABASE_URL);
    console.log(`[API Handler] Initializing DB pool. Has Postgres URL: ${hasPgUrl}`);
    dbReady = testConnectionAndMigrate().then(res => {
      console.log(`[API Handler] testConnectionAndMigrate finished. dbConnected=${isDbConnected()}, dbType=${getDbType()}`);
      return res;
    }).catch(err => {
      console.error('[API Handler] testConnectionAndMigrate failed:', err);
      return false;
    });
  }
  return dbReady;
}

module.exports = async (req, res) => {
  console.log(`[API Incoming Request] ${req.method} ${req.url}`);
  await ensureDb();
  const app = require('../server.js');
  return app(req, res);
};
