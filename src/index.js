const http = require('http');
const config = require('./config/env');
const Database = require('./core/Database');
const createApp = require('./app');

const app = createApp();
const server = http.createServer(app);

async function startServer() {
  console.log('--------------------------------------------------');
  console.log('  Deceit Server — Starting (OOP Architecture)...');
  console.log('--------------------------------------------------');

  const dbReady = await Database.getInstance().init();
  if (!dbReady) {
    console.warn('[Warning] MySQL connection offline. Dependent endpoints will return errors.');
  }

  server.listen(config.port, () => {
    console.log(`==================================================`);
    console.log(`  🚀 Deceit Server active on port ${config.port}`);
    console.log(`  💻 Dashboard: http://localhost:${config.port}/dashboard.html`);
    console.log(`  🔐 Login:     http://localhost:${config.port}/login.html`);
    console.log(`==================================================`);
  });
}

startServer();

module.exports = server;
