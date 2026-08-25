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

  // Handle Phusion Passenger (cPanel) vs Standalone Node.js
  if (typeof(PhusionPassenger) !== 'undefined') {
    server.listen('passenger');
    console.log('[Server] 🚀 Bound to Phusion Passenger IPC Socket.');
  } else {
    const port = process.env.PORT || config.port || 3002;
    server.listen(port, () => {
      console.log(`==================================================`);
      console.log(`  🚀 Deceit Server active on port ${port}`);
      console.log(`  💻 Dashboard: http://localhost:${port}/dashboard.html`);
      console.log(`  🔐 Login:     http://localhost:${port}/login.html`);
      console.log(`==================================================`);
    });
  }
}

startServer();

module.exports = server;
