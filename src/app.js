const express = require('express');
const path = require('path');
const cors = require('cors');
const cookieParser = require('cookie-parser');

const authRoutes = require('./modules/auth/auth.routes');
const playerRoutes = require('./modules/player/player.routes');
const feedbackRoutes = require('./modules/feedback/feedback.routes');
const issueRoutes = require('./modules/issue/issue.routes');
const statsRoutes = require('./modules/stats/stats.routes');
const ErrorMiddleware = require('./middleware/error.middleware');

function createApp() {
  const app = express();

  // Core Middlewares
  app.use(cors({ origin: true, credentials: true }));
  app.use(express.json({ limit: '10mb' }));
  app.use(express.urlencoded({ extended: true, limit: '10mb' }));
  app.use(cookieParser());

  // Static Admin Dashboard UI
  app.use(express.static(path.join(__dirname, '../public')));

  // Plug-and-Play Feature Module Routes
  app.use('/api/auth', authRoutes);
  app.use('/api/players', playerRoutes);
  app.use('/api/feedback', feedbackRoutes);
  app.use('/api/issues', issueRoutes);
  app.use('/api/stats', statsRoutes);

  // Fallback handler for Web Dashboard pages (works under root /, /deceit/, /DServer/, etc.)
  app.get('*', (req, res, next) => {
    if (req.path.includes('/api/')) {
      return next();
    }
    if (req.path.endsWith('.css') || req.path.endsWith('.js') || req.path.endsWith('.png') || req.path.endsWith('.jpg') || req.path.endsWith('.ico')) {
      return next();
    }
    if (req.path.endsWith('dashboard.html')) {
      return res.sendFile(path.join(__dirname, '../public/dashboard.html'));
    }
    return res.sendFile(path.join(__dirname, '../public/login.html'));
  });

  // Central Error Middleware
  app.use(ErrorMiddleware.handle);

  return app;
}

module.exports = createApp;
