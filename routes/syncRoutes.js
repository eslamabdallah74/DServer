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

// HTTP REST Room Endpoints with Pusher real-time triggers
const roomManager = require('../roomManager');
const chatEngine = require('../chatEngine');
const { broadcastToRoom } = require('../src/pusherClient');
const { assignRoles, startPhase, broadcastSanitizedRoomSnapshot, checkAllReady } = require('../gameEngine');

router.post('/create', async (req, res) => {
  try {
    const { nickname, settings } = req.body || {};
    const { room, hostPlayer, reconnectToken } = roomManager.createRoom(nickname, settings || {});
    await roomManager.saveRoomDb(room);
    broadcastSanitizedRoomSnapshot(null, room);
    return res.status(201).json({
      success: true,
      roomCode: room.roomCode,
      playerId: hostPlayer.playerId,
      reconnectToken,
      room: room.toPublicSnapshot ? room.toPublicSnapshot() : room,
    });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/join', async (req, res) => {
  try {
    const { roomCode, nickname } = req.body || {};
    await roomManager.loadRoomDb(roomCode);
    const result = roomManager.joinRoom(roomCode, nickname);
    if (result.error) {
      return res.status(400).json({ success: false, error: result.error });
    }
    await roomManager.saveRoomDb(result.room);
    broadcastSanitizedRoomSnapshot(null, result.room);
    return res.json({
      success: true,
      roomCode: result.room.roomCode,
      playerId: result.player.playerId,
      reconnectToken: result.reconnectToken,
      room: result.room.toPublicSnapshot ? result.room.toPublicSnapshot() : result.room,
    });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/:roomCode/start', async (req, res) => {
  try {
    const { roomCode } = req.params;
    const { playerId } = req.body || {};
    await roomManager.loadRoomDb(roomCode);
    const room = roomManager.getRoom(roomCode);
    if (!room) return res.status(404).json({ success: false, error: 'ROOM_NOT_FOUND' });
    if (room.hostPlayerId !== playerId) return res.status(403).json({ success: false, error: 'NOT_HOST' });

    assignRoles(room);
    startPhase(null, room, 'ROLE_REVEAL', 8, () => {
      const { advanceMatchLoop } = require('../gameEngine');
      advanceMatchLoop(null, room);
    });

    broadcastSanitizedRoomSnapshot(null, room);
    return res.json({ success: true });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/:roomCode/action', async (req, res) => {
  try {
    const { roomCode } = req.params;
    const { playerId, targetPlayerId } = req.body || {};
    const room = roomManager.getRoom(roomCode);
    if (!room) return res.status(404).json({ success: false, error: 'ROOM_NOT_FOUND' });

    const player = room.players.find(p => p.playerId === playerId);
    if (!player || !player.isAlive) return res.status(400).json({ success: false, error: 'INVALID_PLAYER' });

    room.nightActions.set(playerId, { targetPlayerId });
    broadcastSanitizedRoomSnapshot(null, room);
    return res.json({ success: true });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/:roomCode/vote', async (req, res) => {
  try {
    const { roomCode } = req.params;
    const { playerId, targetPlayerId } = req.body || {};
    const room = roomManager.getRoom(roomCode);
    if (!room) return res.status(404).json({ success: false, error: 'ROOM_NOT_FOUND' });

    const player = room.players.find(p => p.playerId === playerId);
    if (!player || !player.isAlive) return res.status(400).json({ success: false, error: 'INVALID_PLAYER' });

    room.votes.set(playerId, targetPlayerId);

    const voteTally = {};
    room.votes.forEach((target) => {
      voteTally[target] = (voteTally[target] || 0) + 1;
    });

    broadcastToRoom(room.roomCode, 's_vote_update', { voteTally, totalVoters: room.players.filter(p => p.isAlive).length });
    broadcastSanitizedRoomSnapshot(null, room);
    return res.json({ success: true });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/:roomCode/ready', async (req, res) => {
  try {
    const { roomCode } = req.params;
    const { playerId } = req.body || {};
    const room = roomManager.getRoom(roomCode);
    if (!room) return res.status(404).json({ success: false, error: 'ROOM_NOT_FOUND' });

    const player = room.players.find(p => p.playerId === playerId);
    if (player && player.isAlive) {
      player.isReady = true;
      broadcastSanitizedRoomSnapshot(null, room);
      checkAllReady(room, null);
    }
    return res.json({ success: true });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/:roomCode/chat', async (req, res) => {
  try {
    const { roomCode } = req.params;
    const { playerId, text } = req.body || {};
    const room = roomManager.getRoom(roomCode);
    if (!room) return res.status(404).json({ success: false, error: 'ROOM_NOT_FOUND' });

    const player = room.players.find(p => p.playerId === playerId);
    if (!player) return res.status(400).json({ success: false, error: 'PLAYER_NOT_FOUND' });

    const result = chatEngine.processMessage(null, room, player, text);
    if (result.error) {
      return res.status(400).json({ success: false, error: result.error });
    }
    return res.json({ success: true, message: result.message });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

router.get('/:roomCode', async (req, res) => {
  try {
    const room = await roomManager.getRoomAsync(req.params.roomCode);
    if (!room) {
      return res.status(404).json({ success: false, error: 'ROOM_NOT_FOUND' });
    }
    return res.json({ success: true, room: room.toPublicSnapshot ? room.toPublicSnapshot() : room });
  } catch (err) {
    return res.status(500).json({ success: false, error: err.message });
  }
});

module.exports = router;
