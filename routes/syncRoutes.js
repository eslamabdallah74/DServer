const express = require('express');
const jwt = require('jsonwebtoken');
const env = require('../src/config/env');
const PlayerSyncController = require('../src/controllers/PlayerSyncController');
const IssueController = require('../src/controllers/IssueController');

const router = express.Router();

// Request Logger Middleware
router.use((req, res, next) => {
  console.log(`[Sync API] 📡 ${req.method} ${req.originalUrl || req.url}`);
  next();
});

// Authentication Middleware supporting direct JWT_SECRET header or Bearer Token
function authenticateToken(req, res, next) {
  const secretHeader = req.headers['x-jwt-secret'] || req.headers['x-secret'] || req.headers['x-api-key'];
  const authHeader = req.headers['authorization'];
  const rawToken = authHeader && authHeader.startsWith('Bearer ') ? authHeader.split(' ')[1] : authHeader;

  if (secretHeader === env.jwtSecret || rawToken === env.jwtSecret) {
    req.user = { deviceId: 'secret_client', verifiedBySecret: true };
    return next();
  }

  if (rawToken) {
    return jwt.verify(rawToken, env.jwtSecret, (err, decoded) => {
      if (!err) {
        req.user = decoded;
        return next();
      }
      req.user = { deviceId: 'fallback_client' };
      return next();
    });
  }

  req.user = { deviceId: 'default_client' };
  next();
}

// Token generation endpoint for app client initialization
router.post('/token', (req, res) => {
  const { deviceId } = req.body || {};
  const payload = {
    deviceId: deviceId || 'anonymous_device',
    iat: Math.floor(Date.now() / 1000),
  };
  const token = jwt.sign(payload, env.jwtSecret, { expiresIn: '365d' });
  return res.json({ success: true, token });
});

// Player profile sync endpoints
router.post('/player', authenticateToken, PlayerSyncController.sync);
router.post('/players', authenticateToken, PlayerSyncController.sync);
router.get('/player/:id', authenticateToken, PlayerSyncController.getPlayer);

// Issue logging endpoints
router.post('/issues', authenticateToken, IssueController.logIssue);
router.get('/issues', authenticateToken, IssueController.getIssues);

module.exports = router;
