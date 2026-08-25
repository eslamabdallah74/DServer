const express = require('express');
const http = require('http');
const path = require('path');
const cors = require('cors');
const cookieParser = require('cookie-parser');
const config = require('./config/env');
const { initDatabase } = require('./db/connection');

const authRoutes = require('./routes/authRoutes');
const playerRoutes = require('./routes/playerRoutes');
const feedbackRoutes = require('./routes/feedbackRoutes');
const issueRoutes = require('./routes/issueRoutes');
const statsRoutes = require('./routes/statsRoutes');
const errorHandler = require('./middleware/errorHandler');

const app = express();

// Middlewares
app.use(cors({ origin: true, credentials: true }));
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));
app.use(cookieParser());

// Serve Web Admin Dashboard static files
app.use(express.static(path.join(__dirname, '../public')));

// Mount API routes
app.use('/api/auth', authRoutes);
app.use('/api/players', playerRoutes);
app.use('/api/feedback', feedbackRoutes);
app.use('/api/issues', issueRoutes);
app.use('/api/stats', statsRoutes);

// Redirect root to dashboard/login
app.get('/', (req, res) => {
  res.redirect('/login.html');
});

// Central error handler
app.use(errorHandler);

const server = http.createServer(app);

// Initialize DB and Boot Server
async function startServer() {
  console.log('--------------------------------------------------');
  console.log('  Deceit Offline Server — Starting Up...');
  console.log('--------------------------------------------------');

  const dbReady = await initDatabase();
  if (!dbReady) {
    console.warn('[Warning] Server starting with MySQL offline. Some endpoints may return 503.');
  }

  server.listen(config.port, () => {
    console.log(`==================================================`);
    console.log(`  🚀 Deceit Offline Server active on port ${config.port}`);
    console.log(`  💻 Dashboard: http://localhost:${config.port}/dashboard.html`);
    console.log(`  🔐 Login:     http://localhost:${config.port}/login.html`);
    console.log(`==================================================`);
  });
}

startServer();

module.exports = server;
