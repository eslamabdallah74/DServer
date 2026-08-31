const { testConnectionAndMigrate } = require('../db');

let dbReady = null;

async function ensureDb() {
  if (!dbReady) {
    dbReady = testConnectionAndMigrate();
  }
  return dbReady;
}

module.exports = async (req, res) => {
  await ensureDb();
  const app = require('../server.js');
  return app(req, res);
};
