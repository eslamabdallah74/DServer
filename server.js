require('dotenv').config();

// Global Crash Protection — Prevents cPanel Phusion Passenger Process Loop Crashes
process.on('uncaughtException', (err) => {
  console.error('[CRITICAL] Uncaught Exception:', err.stack || err);
});

process.on('unhandledRejection', (reason) => {
  console.error('[CRITICAL] Unhandled Rejection:', reason);
});

const express  = require('express');
const http     = require('http');
const path     = require('path');
const { Server } = require('socket.io');
const cors     = require('cors');
const cookieParser = require('cookie-parser');

const { testConnectionAndMigrate, isDbConnected, getDbType } = require('./db');
const authRoutes  = require('./routes/authRoutes');
const adminRoutes = require('./routes/adminRoutes');
const syncRoutes  = require('./routes/syncRoutes');
const { sanitizeInput, isValidRoomCode, isValidPlayerId } = require('./sanitizer');

const roomManager  = require('./roomManager');
const chatEngine   = require('./chatEngine');
const { assignRoles, startPhase, advanceMatchLoop, broadcastSanitizedRoomSnapshot } = require('./gameEngine');

const { getDashboardHtml } = require('./dashboard');

const app = express();
app.use(cors({ origin: true, credentials: true }));
app.use(express.json());
app.use(cookieParser());

// Strip leading /server prefix if running behind cPanel / Phusion Passenger subpath
app.use((req, res, next) => {
  if (req.url.startsWith('/server/')) {
    req.url = req.url.substring(7);
    req.originalUrl = req.url;
  } else if (req.url === '/server') {
    req.url = '/';
    req.originalUrl = '/';
  }
  next();
});

// Serve Web Admin Dashboard static files & explicit HTML fallback routes
app.use(['/admin', '/server/admin'], express.static(path.join(__dirname, 'public/admin')));
app.use(express.static(path.join(__dirname, 'public/admin')));
app.use(express.static(path.join(__dirname, 'public')));

app.get(['/admin/login', '/admin/login.html', '/server/admin/login.html'], (req, res) => {
  res.sendFile(path.join(__dirname, 'public/admin/login.html'));
});

app.get(['/admin/dashboard', '/admin/dashboard.html', '/server/admin/dashboard.html'], (req, res) => {
  res.sendFile(path.join(__dirname, 'public/admin/dashboard.html'));
});

app.get(['/admin/register', '/admin/register.html', '/server/admin/register.html'], (req, res) => {
  res.sendFile(path.join(__dirname, 'public/admin/register.html'));
});

// Mount API routes supporting both standard and /server subpath for cPanel Passenger
const apiSyncPaths  = ['/api/sync', '/server/api/sync'];
const apiPlayerPaths = ['/api/players', '/server/api/players'];
const apiIssuePaths  = ['/api/issues', '/server/api/issues'];
const apiFbPaths     = ['/api/feedback', '/server/api/feedback'];
const apiStatsPaths  = ['/api/stats', '/server/api/stats'];
const apiAuthPaths   = ['/api/auth', '/server/api/auth'];
const apiAdminPaths  = ['/api/admin', '/server/api/admin'];
const apiRoomPaths   = ['/api/rooms', '/server/api/rooms'];

apiAuthPaths.forEach(p => app.use(p, authRoutes));
apiAdminPaths.forEach(p => app.use(p, adminRoutes));
apiSyncPaths.forEach(p => app.use(p, syncRoutes));
apiPlayerPaths.forEach(p => app.use(p, syncRoutes));
apiIssuePaths.forEach(p => app.use(p, syncRoutes));
apiFbPaths.forEach(p => app.use(p, syncRoutes));
apiStatsPaths.forEach(p => app.use(p, syncRoutes));
apiRoomPaths.forEach(p => app.use(p, syncRoutes));


const startTime = Date.now();

function getStatusPayload() {
  let totalPlayersCount = 0;
  roomManager.rooms.forEach(r => {
    totalPlayersCount += r.players.filter(p => p.isConnected).length;
  });

  const uptimeSeconds = Math.floor((Date.now() - startTime) / 1000);

  return {
    status: 'ok',
    activeRooms: roomManager.rooms.size,
    totalPlayers: totalPlayersCount,
    uptimeSeconds,
  };
}

// Redirect / to /admin/login.html or /server/admin/login.html
app.get('/', (req, res) => {
  const prefix = (req.originalUrl && req.originalUrl.startsWith('/server')) ? '/server' : '';
  res.redirect(`${prefix}/admin/login.html`);
});

// ─── Health check endpoint (Cheap JSON) ───────────────────────────────────────
app.get('/health', (req, res) => {
  res.json({
    status:        'ok',
    activeRooms:   roomManager.rooms ? roomManager.rooms.size : 0,
    totalPlayers:  typeof roomManager.getTotalPlayers === 'function' ? roomManager.getTotalPlayers() : 0,
    uptimeSeconds: Math.floor(process.uptime()),
  });
});

let latestBroadcast = null;

function setLatestBroadcast(b) {
  latestBroadcast = b;
}

function getLatestBroadcast() {
  return latestBroadcast;
}

app.set('setLatestBroadcast', setLatestBroadcast);
app.set('getLatestBroadcast', getLatestBroadcast);

app.get('/api/announcement', (req, res) => {
  res.json({ announcement: latestBroadcast });
});

// ─── Status JSON API (for dashboard auto-refresh) ────────────────────────────
app.get('/api/status', (req, res) => {
  res.json(getStatusPayload());
});

const server = http.createServer(app);

// Prepend raw HTTP request listener so /server/socket.io is rewritten BEFORE Socket.IO engine.io inspects it
server.prependListener('request', (req, res) => {
  if (req.url && req.url.startsWith('/server/socket.io')) {
    req.url = req.url.substring(7);
  }
});

const io     = new Server(server, {
  cors: { origin: '*', methods: ['GET', 'POST'] },
  pingTimeout:  20000,
  pingInterval: 10000,
});

app.set('io', io);

// Express handler for Vercel/Serverless environments to process Socket.IO polling & Engine.IO requests
app.all(['/socket.io*', '/server/socket.io*'], (req, res) => {
  if (req.url && req.url.startsWith('/server/socket.io')) {
    req.url = req.url.substring(7);
  }
  if (io && io.engine) {
    io.engine.handleRequest(req, res);
  } else {
    res.status(500).send('Socket.IO Engine not initialized');
  }
});

// Test database connection and apply migrations on boot
testConnectionAndMigrate().then(connected => {
  if (connected) {
    console.log('[Server Boot] MySQL Database & Migrations Ready.');
  } else {
    console.log('[Server Boot] Running in Memory-Only Mode for Game Rooms. (MySQL unavailable)');
  }
}).catch(err => {
  console.error('[Server Boot Error]:', err.message);
});

const PORT = process.env.PORT || 3001;

// ─── Socket.IO event handlers ─────────────────────────────────────────────────
io.on('connection', socket => {
  console.log(`[+] Socket: ${socket.id}`);

  // Send latest persistent broadcast to newly connected client
  if (latestBroadcast) {
    socket.emit('s_system_broadcast', latestBroadcast);
  }

  // ── 1. Create Room ──────────────────────────────────────────────────────────
  socket.on('c_create_room', async ({ nickname, settings } = {}) => {
    try {
      const { room, hostPlayer, reconnectToken } = roomManager.createRoom(nickname, settings || {});
      await roomManager.saveRoomDb(room);
      roomManager.bindSocket(room.roomCode, hostPlayer.playerId, socket.id);
      socket.join(room.roomCode);

      socket.emit('s_create_room_response', {
        success:        true,
        roomCode:       room.roomCode,
        playerId:       hostPlayer.playerId,
        reconnectToken: reconnectToken,
      });

      broadcastSanitizedRoomSnapshot(io, room);
      console.log(`[Room ${room.roomCode}] Created by "${nickname}"`);
    } catch (err) {
      console.error('[c_create_room] Error:', err);
      socket.emit('s_error', { code: 'CREATE_FAILED', message: 'فشل إنشاء الغرفة' });
    }
  });

  // ── 1.5 Add Bot ─────────────────────────────────────────────────────────────
  socket.on('c_add_bot', async ({ roomCode, playerId } = {}) => {
    await roomManager.loadRoomDb(roomCode);
    const room = roomManager.getRoom(roomCode);
    if (!room) return;
    
    // Only host can add bots
    const host = room.players.find(p => p.isHost);
    if (!host || host.playerId !== playerId) return;

    const res = roomManager.addBot(roomCode);
    if (res.error) {
      const messages = {
        ROOM_NOT_FOUND:      'الغرفة غير موجودة',
        GAME_ALREADY_STARTED:'اللعبة بدأت بالفعل',
        ROOM_FULL:           'الغرفة ممتلئة',
      };
      socket.emit('s_error', { code: res.error, message: messages[res.error] || 'تعذر إضافة بوت' });
      return;
    }

    // Broadcast updated room state
    broadcastSanitizedRoomSnapshot(io, res.room);
  });

  // ── 1.6 Remove Bot ──────────────────────────────────────────────────────────
  socket.on('c_remove_bot', async ({ roomCode, playerId, botPlayerId } = {}) => {
    await roomManager.loadRoomDb(roomCode);
    const room = roomManager.getRoom(roomCode);
    if (!room) return;
    
    // Only host can remove bots
    const host = room.players.find(p => p.isHost);
    if (!host || host.playerId !== playerId) return;

    const res = roomManager.removeBot(roomCode, botPlayerId);
    if (res.error) {
      // Ignore errors silently for remove
      return;
    }

    // Broadcast updated room state
    broadcastSanitizedRoomSnapshot(io, res.room);
  });

  // ── 2. Join Room ────────────────────────────────────────────────────────────
  socket.on('c_join_room', async ({ roomCode, nickname } = {}) => {
    await roomManager.loadRoomDb(roomCode);
    const res = roomManager.joinRoom(roomCode, nickname);
    if (res.error) {
      const messages = {
        ROOM_NOT_FOUND:      'الغرفة غير موجودة',
        GAME_ALREADY_STARTED:'اللعبة بدأت بالفعل',
        ROOM_FULL:           'الغرفة ممتلئة',
      };
      socket.emit('s_error', { code: res.error, message: messages[res.error] || 'تعذر الانضمام' });
      return;
    }

    const { room, player, reconnectToken } = res;
    roomManager.bindSocket(room.roomCode, player.playerId, socket.id);
    socket.join(room.roomCode);

    socket.emit('s_join_room_response', {
      success:        true,
      roomCode:       room.roomCode,
      playerId:       player.playerId,
      reconnectToken: reconnectToken,
    });

    broadcastSanitizedRoomSnapshot(io, room);
    console.log(`[Room ${room.roomCode}] "${nickname}" joined (${room.players.length} players)`);
  });

  // ── 3. Reconnect ────────────────────────────────────────────────────────────
  socket.on('c_reconnect', async ({ roomCode, reconnectToken } = {}) => {
    await roomManager.loadRoomDb(roomCode);
    const res = roomManager.reconnectPlayer(roomCode, reconnectToken, socket.id);
    if (res.error) {
      socket.emit('s_error', { code: res.error, message: 'انتهت جلسة إعادة الاتصال' });
      return;
    }

    const { room, player } = res;
    socket.join(room.roomCode);

    socket.emit('s_reconnect_response', {
      success:  true,
      roomCode: room.roomCode,
      playerId: player.playerId,
    });

    // Re-emit private role payload directly to caller socket on reconnect
    if (player.role) {
      const shadowAllies = player.faction === 'shadow'
        ? room.players
            .filter(a => a.faction === 'shadow' && a.playerId !== player.playerId)
            .map(a => ({ playerId: a.playerId, nickname: a.nickname, role: a.role, isAlive: a.isAlive }))
        : [];

      socket.emit('s_role_assigned', {
        role:         player.role,
        faction:      player.faction,
        shadowAllies: shadowAllies,
      });
    }

    // Replay permitted chat history
    const history = chatEngine.getPermittedHistory(room, player);
    socket.emit('s_chat_history', { history });

    broadcastSanitizedRoomSnapshot(io, room);
    console.log(`[Room ${room.roomCode}] "${player.nickname}" reconnected`);
  });

  // ── 3.5. Update Settings (Host only) ─────────────────────────────────────────
  socket.on('c_update_settings', ({ roomCode, playerId, settings } = {}) => {
    const room = roomManager.getRoom(roomCode);
    if (!room) return;

    if (room.hostPlayerId !== playerId) {
      socket.emit('s_error', { code: 'NOT_HOST', message: 'فقط المضيف يمكنه تغيير الإعدادات' });
      return;
    }
    if (room.phase !== 'LOBBY') {
      socket.emit('s_error', { code: 'ALREADY_STARTED', message: 'لا يمكن تغيير الإعدادات بعد بدء اللعبة' });
      return;
    }

    // Merge new settings with existing
    if (settings && typeof settings === 'object') {
      room.settings = { ...room.settings, ...settings };
      broadcastSanitizedRoomSnapshot(io, room);
      console.log(`[Room ${room.roomCode}] Settings updated by host:`, settings);
    }
  });

  // ── 4. Start Game (Host only) ───────────────────────────────────────────────
  socket.on('c_start_game', ({ roomCode, playerId } = {}) => {
    const room = roomManager.getRoom(roomCode);
    if (!room) return;

    if (room.hostPlayerId !== playerId) {
      socket.emit('s_error', { code: 'NOT_HOST', message: 'فقط المضيف يمكنه بدء اللعبة' });
      return;
    }
    if (room.phase !== 'LOBBY') {
      socket.emit('s_error', { code: 'ALREADY_STARTED', message: 'اللعبة بدأت بالفعل' });
      return;
    }
    if (room.players.length < 4) {
      socket.emit('s_error', { code: 'NOT_ENOUGH_PLAYERS', message: 'يلزم 4 لاعبين على الأقل لبدء اللعبة' });
      return;
    }

    console.log(`[Room ${room.roomCode}] Starting game with ${room.players.length} players`);

    // Assign roles
    assignRoles(room);

    // Emit private role card to each player
    room.players.forEach(p => {
      if (!p.socketId) return;

      const shadowAllies = p.faction === 'shadow'
        ? room.players
            .filter(a => a.faction === 'shadow' && a.playerId !== p.playerId)
            .map(a => ({ playerId: a.playerId, nickname: a.nickname, role: a.role }))
        : [];

      io.to(p.socketId).emit('s_role_assigned', {
        role:         p.role,
        faction:      p.faction,
        shadowAllies: shadowAllies,
      });
    });

    // ROLE_REVEAL (15s) → then Night 1
    startPhase(io, room, 'ROLE_REVEAL', 15, () => {
      advanceMatchLoop(io, room);
    });
  });

  // ── 5. Submit Night Action ──────────────────────────────────────────────────
  socket.on('c_submit_night_action', ({ roomCode, playerId, targetPlayerId } = {}) => {
    try {
      const room = roomManager.getRoom(roomCode);
      if (!room || room.phase !== 'NIGHT') {
        socket.emit('s_error', { code: 'INVALID_PHASE', message: 'لا يمكنك تنفيذ قدرة الليل في هذه المرحلة' });
        return;
      }

      const actor = room.players.find(p => p.playerId === playerId && p.socketId === socket.id);
      if (!actor || !actor.isAlive) {
        socket.emit('s_error', { code: 'PLAYER_DEAD', message: 'اللاعب غير متاح أو متوفى' });
        return;
      }

      // Idempotency check: Reject duplicate night action submission
      if (room.nightActions.has(actor.playerId)) {
        socket.emit('s_error', { code: 'ACTION_ALREADY_SUBMITTED', message: 'لقد أرسلت قدرتك بالفعل هذه الجولة' });
        return;
      }

      // Record night action
      room.nightActions.set(actor.playerId, {
        actorPlayerId:  actor.playerId,
        roleId:         actor.role,
        targetPlayerId: targetPlayerId,
        submittedAt:    Date.now(),
      });
      
      actor.isReady = true;

      socket.emit('s_night_action_accepted', { success: true, targetPlayerId });
      console.log(`[Room ${room.roomCode}] Night action: ${actor.role} → ${targetPlayerId}`);
      
      broadcastSanitizedRoomSnapshot(io, room);
      const { checkAllReady } = require('./gameEngine');
      checkAllReady(room, io);
    } catch (err) {
      console.error('[c_submit_night_action] Error:', err);
      socket.emit('s_error', { code: 'SERVER_ERROR', message: 'حدث خطأ في تقديم القدرة' });
    }
  });

  // ── 6. Complete Task ────────────────────────────────────────────────────────
  socket.on('c_complete_task', ({ roomCode, playerId, taskId } = {}) => {
    try {
      const room = roomManager.getRoom(roomCode);
      if (!room || room.phase !== 'DAY') return;

      const player = room.players.find(p => p.playerId === playerId && p.socketId === socket.id);
      if (!player || !player.isAlive) return;

      player.completedTasks += 1;

      const totalRequired  = room.players.filter(p => p.isAlive).length * 2;
      const totalCompleted = room.players.reduce((sum, p) => sum + p.completedTasks, 0);

      room.taskProgress = Math.min(1.0, totalCompleted / totalRequired);
      broadcastSanitizedRoomSnapshot(io, room);

      // If tasks hit 100%, kingdom wins immediately
      if (room.taskProgress >= 1.0) {
        const { evaluateVictory } = require('./victoryEngine');
        const victory = evaluateVictory(room);
        if (victory.isDecided) {
          room.victoryOutcome = victory;
          startPhase(io, room, 'GAME_OVER', 0, null);
        }
      }
    } catch (err) {
      console.error('[c_complete_task] Error:', err);
    }
  });

  // ── 7. Cast Vote ────────────────────────────────────────────────────────────
  socket.on('c_cast_vote', ({ roomCode, playerId, targetPlayerId } = {}) => {
    try {
      const room = roomManager.getRoom(roomCode);
      if (!room || room.phase !== 'VOTING') {
        socket.emit('s_error', { code: 'INVALID_PHASE', message: 'التصويت غير متاح في هذه المرحلة' });
        return;
      }

      const voter = room.players.find(p => p.playerId === playerId && p.socketId === socket.id);
      if (!voter || !voter.isAlive) {
        socket.emit('s_error', { code: 'PLAYER_DEAD', message: 'اللاعب غير متاح أو متوفى' });
        return;
      }

      // Idempotency check: Reject duplicate vote
      if (room.votes.has(voter.playerId)) {
        socket.emit('s_error', { code: 'ALREADY_VOTED', message: 'لقد قمت بالتصويت بالفعل هذه الجولة' });
        return;
      }

      // Can't vote for self
      if (voter.playerId === targetPlayerId) {
        socket.emit('s_error', { code: 'SELF_VOTE', message: 'لا يمكنك التصويت على نفسك' });
        return;
      }

      const target = room.players.find(p => p.playerId === targetPlayerId);
      if (!target || !target.isAlive) {
        socket.emit('s_error', { code: 'INVALID_TARGET', message: 'الهدف غير صالح أو متوفى' });
        return;
      }

      room.votes.set(voter.playerId, targetPlayerId);
      voter.isReady = true; // Auto-ready after voting
      socket.emit('s_vote_accepted', { success: true, targetPlayerId });

      // Broadcast vote count update so everyone can see progress
      const voteTally = {};
      room.votes.forEach((tid, vid) => {
        voteTally[tid] = (voteTally[tid] || 0) + 1;
      });
      io.to(room.roomCode).emit('s_vote_update', { voteTally, totalVoters: room.players.filter(p => p.isAlive).length });
      
      broadcastSanitizedRoomSnapshot(io, room);
      const { checkAllReady } = require('./gameEngine');
      checkAllReady(room, io);
    } catch (err) {
      console.error('[c_cast_vote] Error:', err);
      socket.emit('s_error', { code: 'SERVER_ERROR', message: 'حدث خطأ في عملية التصويت' });
    }
  });
  
  // ── 7.5 Ready Status ────────────────────────────────────────────────────────
  socket.on('c_player_ready', ({ roomCode, playerId } = {}) => {
    const room = roomManager.getRoom(roomCode);
    if (!room) return;

    const player = room.players.find(p => p.playerId === playerId);
    if (!player || !player.isAlive) return;

    player.isReady = true;
    
    broadcastSanitizedRoomSnapshot(io, room);
    const { checkAllReady } = require('./gameEngine');
    checkAllReady(room, io);
  });

  // ── 8. Send Chat ────────────────────────────────────────────────────────────
  socket.on('c_send_chat', ({ roomCode, playerId, text } = {}) => {
    const room = roomManager.getRoom(roomCode);
    if (!room) return;

    const player = room.players.find(p => p.playerId === playerId);
    if (!player) return;

    const result = chatEngine.processMessage(io, room, player, text);
    if (result.error) {
      const messages = {
        RATE_LIMIT_EXCEEDED: 'إرسال سريع جداً — انتظر لحظة',
        PLAYER_SILENCED:     'أنت صامت ولا يمكنك الكلام هذه الجولة',
        CHAT_NOT_PERMITTED:  'الدردشة غير متاحة في هذه المرحلة',
        EMPTY_MESSAGE:       'الرسالة فارغة',
      };
      socket.emit('s_error', { code: result.error, message: messages[result.error] || 'تعذر إرسال الرسالة' });
    }
  });

  // ── 8.5 Leave Room ─────────────────────────────────────────────────────────
  socket.on('c_leave_room', ({ roomCode, playerId } = {}) => {
    const res = roomManager.leaveRoom(roomCode, playerId);
    socket.leave(roomCode);
    if (!res.error && !res.roomDestroyed) {
      broadcastSanitizedRoomSnapshot(io, res.room);
    }
  });

  // ── 9. Disconnect ───────────────────────────────────────────────────────────
  socket.on('disconnect', reason => {
    console.log(`[-] Socket: ${socket.id} (${reason})`);
    chatEngine.cleanupSocket(socket.id);

    roomManager.rooms.forEach((room, roomCode) => {
      const player = room.players.find(p => p.socketId === socket.id);
      if (player) {
        const res = roomManager.handleDisconnect(roomCode, player.playerId, (r, p) => {
          console.log(`[Room ${r.roomCode}] "${p.nickname}" disconnect expired`);
          broadcastSanitizedRoomSnapshot(io, r);
        });
        if (!res.roomDestroyed) {
          broadcastSanitizedRoomSnapshot(io, room);
        }
      }
    });
  });
});

// ─── Graceful Shutdown ────────────────────────────────────────────────────────
function gracefulShutdown(signal) {
  console.log(`[Shutdown] Received ${signal}. Closing server gracefully...`);
  roomManager.stopCleanupWorker();

  try {
    io.close(() => {
      server.close(() => {
        console.log('[Shutdown] Server closed cleanly.');
        process.exit(0);
      });
    });
  } catch (err) {
    process.exit(0);
  }
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT',  () => gracefulShutdown('SIGINT'));

// Start server on process.env.PORT or 3001 (supports Phusion Passenger Unix domain socket)
const BIND_PORT = process.env.PORT || 3001;

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.warn(`[Port Warning] Port ${BIND_PORT} is already in use by an active server instance.`);
  } else {
    console.error('[Server Error]', err.message);
  }
});

if (!process.env.VERCEL) {
  server.listen(BIND_PORT, () => {
    console.log('=================================================');
    console.log('  Deceit Online — Authoritative Server v2 (HARDENED)');
    console.log(`  Listening on: ${BIND_PORT}`);
    console.log('=================================================');

    // Trigger DB connection & migrations asynchronously after HTTP server is bound
    testConnectionAndMigrate().catch(err => {
      console.warn('[DB] Non-fatal background migration error:', err.message);
    });
  });
}

module.exports = app;
module.exports.server = server;
