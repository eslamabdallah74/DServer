const express = require('express');
const path = require('path');
const fs = require('fs');
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

  // Normalize incoming URLs for cPanel Passenger Sub-Paths & API routes
  app.use((req, res, next) => {
    const rawUrl = req.originalUrl || req.url;

    // Log request for easy debugging
    console.log(`[cPanel Request] ${req.method} rawUrl="${rawUrl}" url="${req.url}"`);

    // Normalize API sub-paths (e.g. /deceit/api/auth/login -> /api/auth/login)
    if (rawUrl.includes('/api/')) {
      const apiPath = rawUrl.substring(rawUrl.indexOf('/api/'));
      req.url = apiPath;
    }
    next();
  });

  // Static Admin Dashboard UI
  app.use(express.static(path.join(__dirname, '../public')));

  // Plug-and-Play Feature Module Routes
  app.use('/api/auth', authRoutes);
  app.use('/api/players', playerRoutes);
  app.use('/api/feedback', feedbackRoutes);
  app.use('/api/issues', issueRoutes);
  app.use('/api/stats', statsRoutes);

  // Universal Fallback Middleware for cPanel Passenger Pages & Assets
  app.use((req, res, next) => {
    if (req.method !== 'GET') return next();

    const rawUrl = req.originalUrl || req.url;

    // Do not intercept API calls
    if (rawUrl.includes('/api/')) return next();

    // Serve dashboard.html if requested
    if (rawUrl.includes('dashboard.html')) {
      return res.sendFile(path.join(__dirname, '../public/dashboard.html'));
    }

    // Serve static assets (.css, .js, images, fonts)
    if (rawUrl.match(/\.(css|js|png|jpg|ico|svg|ttf|woff|woff2)$/)) {
      const filename = rawUrl.split('?')[0].split('/').pop();
      let assetDir = '';
      if (filename.endsWith('.css')) assetDir = 'css';
      if (filename.endsWith('.js')) assetDir = 'js';

      const assetPath = path.join(__dirname, '../public', assetDir, filename);
      if (fs.existsSync(assetPath)) {
        return res.sendFile(assetPath);
      }
    }

    // Default fallback: Serve login.html for any root or page navigation
    return res.sendFile(path.join(__dirname, '../public/login.html'));
  });

  // Central Error Middleware
  app.use(ErrorMiddleware.handle);

  return app;
}

module.exports = createApp;
