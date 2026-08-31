const express = require('express');
const {
  listUsers,
  findUserById,
  findUserByEmail,
  findUserByUsername,
  createUser,
  countAdmins,
  updateUserRole,
  updateUserCoins,
  toggleUserBan,
  resetUserPassword,
  deleteUser,
} = require('../auth/service');
const { dbCheck, authenticateToken, requireAdmin, validateOrigin } = require('../auth/middleware');
const { logAdminAction, listAuditLogs } = require('./auditLogger');
const roomManager = require('../roomManager');

const router = express.Router();

// Admin routes require auth, admin role, and CSRF origin check
router.use(authenticateToken);
router.use(requireAdmin);
router.use(validateOrigin);

// ── User Management ─────────────────────────────────────────────────────────

router.get('/users', dbCheck, async (req, res) => {
  try {
    const { search = '', limit = 50, offset = 0 } = req.query;
    const result = await listUsers({ search, limit, offset });
    return res.json(result);
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Admin Create User Endpoint
router.post('/users', dbCheck, async (req, res) => {
  try {
    const { username, email, password, role = 'player', coins = 0 } = req.body || {};

    if (!username || !email || !password) {
      return res.status(400).json({ error: 'INVALID_INPUT', message: 'اسم المستخدم والبريد وكلمة المرور مطلوبة.' });
    }

    const existingEmail = await findUserByEmail(email.trim().toLowerCase());
    if (existingEmail) {
      return res.status(400).json({ error: 'EMAIL_TAKEN', message: 'البريد الإلكتروني مسجل بالفعل.' });
    }

    const existingUser = await findUserByUsername(username.trim());
    if (existingUser) {
      return res.status(400).json({ error: 'USERNAME_TAKEN', message: 'اسم المستخدم مسجل بالفعل.' });
    }

    const userDTO = await createUser({
      username: username.trim(),
      email: email.trim().toLowerCase(),
      password,
      role: role === 'admin' ? 'admin' : 'player',
      coins: parseInt(coins, 10) || 0,
    });

    await logAdminAction(req, 'USER_CREATE', 'user', userDTO.id, {
      username: userDTO.username,
      role: userDTO.role,
    });

    return res.status(201).json({ success: true, user: userDTO });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Admin Change Role (Self demote blocked)
router.put('/users/:id/role', dbCheck, async (req, res) => {
  try {
    const targetUserId = parseInt(req.params.id, 10);
    const { role } = req.body || {};

    if (!['admin', 'player'].includes(role)) {
      return res.status(400).json({ error: 'INVALID_ROLE', message: 'الدور يجب أن يكون admin أو player.' });
    }

    // Invariant 1: Admin CANNOT change their own rank/role!
    if (targetUserId === req.user.id) {
      return res.status(400).json({ error: 'CANNOT_DEMOTE_SELF', message: 'لا يمكنك تغيير رتبتك أو سلب صلاحية الأدمن من حسابك الشخصي.' });
    }

    const targetUser = await findUserById(targetUserId);
    if (!targetUser) {
      return res.status(404).json({ error: 'USER_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }

    // Invariant 2: Cannot demote last admin
    if (targetUser.role === 'admin' && role === 'player') {
      const totalAdmins = await countAdmins();
      if (totalAdmins <= 1) {
        return res.status(400).json({ error: 'LAST_ADMIN_PROTECTION', message: 'لا يمكنك تخفيض رتبة الأدمن الوحيد المتبقي في النظام.' });
      }
    }

    const updatedUser = await updateUserRole(targetUserId, role);

    await logAdminAction(req, 'ROLE_CHANGE', 'user', targetUserId, {
      oldRole: targetUser.role,
      newRole: role,
      targetUsername: targetUser.username,
    });

    return res.json({ success: true, user: updatedUser });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Admin Edit Coins
router.put('/users/:id/coins', dbCheck, async (req, res) => {
  try {
    const targetUserId = parseInt(req.params.id, 10);
    const { coins } = req.body || {};

    const numCoins = parseInt(coins, 10);
    if (isNaN(numCoins) || numCoins < 0) {
      return res.status(400).json({ error: 'INVALID_COINS', message: 'عدد العملات يجب أن يكون رقماً موجباً.' });
    }

    const targetUser = await findUserById(targetUserId);
    if (!targetUser) {
      return res.status(404).json({ error: 'USER_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }

    const updatedUser = await updateUserCoins(targetUserId, numCoins);

    await logAdminAction(req, 'COIN_EDIT', 'user', targetUserId, {
      oldCoins: targetUser.coins,
      newCoins: numCoins,
      targetUsername: targetUser.username,
    });

    return res.json({ success: true, user: updatedUser });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Admin Ban / Unban User
router.put('/users/:id/ban', dbCheck, async (req, res) => {
  try {
    const targetUserId = parseInt(req.params.id, 10);
    const { isBanned } = req.body || {};

    if (targetUserId === req.user.id) {
      return res.status(400).json({ error: 'CANNOT_BAN_SELF', message: 'لا يمكنك حظر حسابك الشخصي.' });
    }

    const targetUser = await findUserById(targetUserId);
    if (!targetUser) {
      return res.status(404).json({ error: 'USER_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }

    const updatedUser = await toggleUserBan(targetUserId, Boolean(isBanned));

    await logAdminAction(req, isBanned ? 'USER_BAN' : 'USER_UNBAN', 'user', targetUserId, {
      targetUsername: targetUser.username,
    });

    return res.json({ success: true, user: updatedUser });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Admin Reset Password
router.put('/users/:id/password', dbCheck, async (req, res) => {
  try {
    const targetUserId = parseInt(req.params.id, 10);
    const { newPassword } = req.body || {};

    if (!newPassword || typeof newPassword !== 'string' || newPassword.length < 6) {
      return res.status(400).json({ error: 'INVALID_PASSWORD', message: 'كلمة المرور الجديدة يجب أن لا تقل عن 6 أحرف.' });
    }

    const targetUser = await findUserById(targetUserId);
    if (!targetUser) {
      return res.status(404).json({ error: 'USER_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }

    const updatedUser = await resetUserPassword(targetUserId, newPassword);

    await logAdminAction(req, 'PASSWORD_RESET', 'user', targetUserId, {
      targetUsername: targetUser.username,
    });

    return res.json({ success: true, message: `تم إعادة تعيين كلمة المرور للمستخدم ${targetUser.username} بنجاح.` });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Admin Delete User
router.delete('/users/:id', dbCheck, async (req, res) => {
  try {
    const targetUserId = parseInt(req.params.id, 10);

    // Invariant 1: Admin cannot delete self
    if (targetUserId === req.user.id) {
      return res.status(400).json({ error: 'CANNOT_DELETE_SELF', message: 'لا يمكنك حذف حسابك الشخصي.' });
    }

    const targetUser = await findUserById(targetUserId);
    if (!targetUser) {
      return res.status(404).json({ error: 'USER_NOT_FOUND', message: 'المستخدم غير موجود.' });
    }

    // Invariant 2: Cannot delete last admin
    if (targetUser.role === 'admin') {
      const totalAdmins = await countAdmins();
      if (totalAdmins <= 1) {
        return res.status(400).json({ error: 'LAST_ADMIN_PROTECTION', message: 'لا يمكنك حذف الأدمن الوحيد المتبقي في النظام.' });
      }
    }

    await deleteUser(targetUserId);

    await logAdminAction(req, 'USER_DELETE', 'user', targetUserId, {
      deletedUsername: targetUser.username,
      deletedEmail: targetUser.email,
      deletedRole: targetUser.role,
    });

    return res.json({ success: true, message: 'تم حذف الحساب بنجاح.' });
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// ── Audit Logs ──────────────────────────────────────────────────────────────

router.get('/audit-logs', dbCheck, async (req, res) => {
  try {
    const { limit = 50, offset = 0 } = req.query;
    const result = await listAuditLogs({ limit, offset });
    return res.json(result);
  } catch (err) {
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// ── Global Server Broadcast ──────────────────────────────────────────────────

router.post('/broadcast', (req, res) => {
  const { message } = req.body || {};
  if (!message || typeof message !== 'string' || !message.trim()) {
    return res.status(400).json({ error: 'INVALID_MESSAGE', message: 'يرجى كتابة نص الإعلان.' });
  }

  const broadcastObj = {
    id: Date.now().toString(),
    message: message.trim(),
    timestamp: Date.now(),
  };

  const setLatestBroadcast = req.app.get('setLatestBroadcast');
  if (typeof setLatestBroadcast === 'function') {
    setLatestBroadcast(broadcastObj);
  }

  const io = req.app.get('io');
  if (io) {
    io.emit('s_system_broadcast', broadcastObj);
  }

  logAdminAction(req, 'SERVER_BROADCAST', 'system', 'global', { message: message.trim() });

  return res.json({ success: true, message: 'تم إرسال وحفظ التنبيه لجميع اللاعبين بنجاح.' });
});

// ── Room & Game Controls (In-Memory) ────────────────────────────────────────

router.get('/rooms', (req, res) => {
  const roomsList = [];
  roomManager.rooms.forEach((room, code) => {
    roomsList.push({
      roomCode: code,
      phase: room.phase,
      round: room.round || 0,
      totalPlayers: room.players.length,
      humanPlayers: room.players.filter(p => !p.isBot).length,
      botPlayers: room.players.filter(p => p.isBot).length,
      createdAt: room.createdAt,
      lastActivityAt: room.lastActivityAt,
      players: room.players.map(p => ({
        playerId: p.playerId,
        nickname: p.nickname,
        isHost: p.isHost,
        isBot: p.isBot,
        isAlive: p.isAlive,
        role: p.role,
        isConnected: p.isConnected,
      })),
    });
  });

  return res.json({ rooms: roomsList });
});

// Admin Add Bot to Room
router.post('/rooms/:roomCode/add-bot', (req, res) => {
  const roomCode = req.params.roomCode;
  const room = roomManager.getRoom(roomCode);

  if (!room) {
    return res.status(404).json({ error: 'ROOM_NOT_FOUND', message: 'الغرفة غير موجودة.' });
  }

  if (room.phase !== 'LOBBY') {
    return res.status(400).json({ error: 'NOT_IN_LOBBY', message: 'يمكن إضافة البوتات في مرحلة اللوبي فقط.' });
  }

  const botRes = roomManager.addBot(roomCode);
  if (botRes.error) {
    return res.status(400).json({ error: botRes.error, message: 'تعذر إضافة البوت.' });
  }

  const io = req.app.get('io');
  if (io) {
    const { broadcastSanitizedRoomSnapshot } = require('../gameEngine');
    broadcastSanitizedRoomSnapshot(io, room);
  }

  logAdminAction(req, 'BOT_ADD', 'room', roomCode, { botNickname: botRes.bot.nickname });

  return res.json({ success: true, message: `تمت إضافة البوت "${botRes.bot.nickname}" للغرفة.` });
});

// Admin Force Next Phase
router.post('/rooms/:roomCode/next-phase', (req, res) => {
  const roomCode = req.params.roomCode;
  const room = roomManager.getRoom(roomCode);

  if (!room) {
    return res.status(404).json({ error: 'ROOM_NOT_FOUND', message: 'الغرفة غير موجودة.' });
  }

  const io = req.app.get('io');
  if (io) {
    const { skipPhase } = require('../gameEngine');
    skipPhase(room, io);
  }

  logAdminAction(req, 'FORCE_NEXT_PHASE', 'room', roomCode, { currentPhase: room.phase });

  return res.json({ success: true, message: `تم تخطي المرحلة وتمرير الجولة بنجاح في الغرفة ${roomCode}.` });
});

// Destroy Room Controlled API
router.delete('/rooms/:roomCode', async (req, res) => {
  const roomCode = req.params.roomCode;
  const room = roomManager.getRoom(roomCode);

  if (!room) {
    return res.status(404).json({ error: 'ROOM_NOT_FOUND', message: 'الغرفة غير موجودة.' });
  }

  // 1. Controlled room closing flag
  room.isClosing = true;

  // 2. Clear timers
  if (room.timerInterval) {
    clearInterval(room.timerInterval);
    room.timerInterval = null;
  }
  room.phaseCallback = null;

  // 3. Emit room destroyed event to io
  if (req.app.get('io')) {
    const io = req.app.get('io');
    io.to(roomCode).emit('s_room_destroyed', {
      reason: 'تم إغلاق الغرفة بواسطة المشرف.',
    });
  }

  // 4. Remove room
  roomManager.removeRoom(roomCode);

  await logAdminAction(req, 'ROOM_DESTROY', 'room', roomCode, {
    phase: room.phase,
    playersCount: room.players.length,
  });

  return res.json({ success: true, message: `تم إغلاق الغرفة ${roomCode} بنجاح.` });
});

// Kick Player Controlled API
router.delete('/rooms/:roomCode/players/:playerId', async (req, res) => {
  const { roomCode, playerId } = req.params;
  const room = roomManager.getRoom(roomCode);

  if (!room) {
    return res.status(404).json({ error: 'ROOM_NOT_FOUND', message: 'الغرفة غير موجودة.' });
  }

  const player = room.players.find(p => p.playerId === playerId);
  if (!player) {
    return res.status(404).json({ error: 'PLAYER_NOT_FOUND', message: 'اللاعب غير موجود في الغرفة.' });
  }

  // Emit kick notification if player is connected
  if (req.app.get('io') && player.socketId) {
    const io = req.app.get('io');
    io.to(player.socketId).emit('s_kicked', {
      reason: 'تم طردك من الغرفة بواسطة المشرف.',
    });
  }

  const leaveRes = roomManager.leaveRoom(roomCode, playerId);

  await logAdminAction(req, 'PLAYER_KICK', 'player', playerId, {
    roomCode,
    kickedNickname: player.nickname,
  });

  if (req.app.get('io') && !leaveRes.roomDestroyed && leaveRes.room) {
    const io = req.app.get('io');
    const { broadcastSanitizedRoomSnapshot } = require('../gameEngine');
    if (typeof broadcastSanitizedRoomSnapshot === 'function') {
      broadcastSanitizedRoomSnapshot(io, leaveRes.room);
    }
  }

  return res.json({ success: true, message: `تم طرد اللاعب "${player.nickname}" من الغرفة.` });
});

module.exports = router;
