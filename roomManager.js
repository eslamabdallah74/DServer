/**
 * roomManager.js
 * In-Memory Room Store with Reconnection, Socket Binding, and Automated Room Garbage Collection
 */

const { randomUUID } = require('crypto');

class RoomManager {
  constructor() {
    this.rooms = new Map();
    this.cleanupTimeout = null;
  }

  generateRoomCode() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Avoid 0/O, 1/I confusion
    let code = '';
    do {
      code = Array.from({ length: 4 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
    } while (this.rooms.has(code));
    return code;
  }

  createRoom(hostNickname, customSettings = {}) {
    const crypto = require('crypto');
    const roomCode           = this.generateRoomCode();
    const hostPlayerId       = `p_${crypto.randomUUID().substring(0, 8)}`;
    const rawReconnectToken  = crypto.randomBytes(32).toString('hex');
    const tokenHash          = crypto.createHash('sha256').update(rawReconnectToken).digest('hex');

    const defaultSettings = {
      maxPlayers:           16,
      dayTimerSeconds:      120,   // 2 minutes for discussion/tasks
      nightTimerSeconds:    60,    // 1 minute for night actions
      votingTimerSeconds:   60,    // 1 minute for voting
      tieRule:              'NO_ELIMINATION',
      kingMustSurvive:      true,
      revealEliminatedRole: true,
      enabledRoleIds:       [],
    };

    const hostPlayer = _makePlayer(hostPlayerId, hostNickname || 'Host', true, tokenHash);
    const now = Date.now();

    const room = {
      roomCode,
      hostPlayerId,
      settings:             { ...defaultSettings, ...customSettings },
      phase:                'LOBBY',
      phaseStartedAt:       now,
      phaseEndsAt:          0,
      createdAt:            now,
      lastActivityAt:       now,
      eventSequence:        100,
      round:                0,          // incremented at the start of each NIGHT phase
      players:              [hostPlayer],
      nightActions:         new Map(),
      votes:                new Map(),
      taskProgress:         0,
      darknessActive:       false,
      chatHistory:          [],
      timerInterval:        null,
      phaseCallback:        null,
      vacantThrone:         false,
      lastEliminatedPlayer: null,
      lastEliminatedCause:  null,
      lastNightOutcome:     null,
      victoryOutcome:       null,
    };

    this.rooms.set(roomCode, room);
    this.saveRoomDb(room);
    this.scheduleCleanup();
    return { room, hostPlayer, reconnectToken: rawReconnectToken };
  }

  async saveRoomDb(room) {
    if (!room || !room.roomCode) return;
    try {
      const { isDbConnected, query, getDbType } = require('./db');
      if (!isDbConnected()) return;
      const serializableRoom = {
        ...room,
        nightActions: Object.fromEntries(room.nightActions || []),
        votes: Object.fromEntries(room.votes || []),
        timerInterval: null,
        phaseCallback: null,
      };
      const jsonStr = JSON.stringify(serializableRoom);
      if (getDbType() === 'pg') {
        await query(
          'INSERT INTO active_rooms (room_code, room_data, last_activity_at) VALUES (?, ?, NOW()) ON CONFLICT (room_code) DO UPDATE SET room_data = EXCLUDED.room_data, last_activity_at = NOW()',
          [room.roomCode, jsonStr]
        );
      } else {
        await query(
          'INSERT INTO active_rooms (room_code, room_data, last_activity_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE room_data = VALUES(room_data), last_activity_at = NOW()',
          [room.roomCode, jsonStr]
        );
      }
    } catch (e) {
      console.warn(`[RoomManager DB Save Error ${room.roomCode}]:`, e.message);
    }
  }

  async loadRoomDb(roomCode) {
    if (!roomCode) return null;
    const code = roomCode.trim().toUpperCase();
    if (this.rooms.has(code)) return this.rooms.get(code);

    try {
      const { isDbConnected, query } = require('./db');
      if (!isDbConnected()) return null;
      const [rows] = await query('SELECT room_data FROM active_rooms WHERE room_code = ?', [code]);
      if (rows && rows.length > 0) {
        const rawData = typeof rows[0].room_data === 'string' ? JSON.parse(rows[0].room_data) : rows[0].room_data;
        rawData.nightActions = new Map(Object.entries(rawData.nightActions || {}));
        rawData.votes = new Map(Object.entries(rawData.votes || {}));
        this.rooms.set(code, rawData);
        this.scheduleCleanup();
        console.log(`[RoomManager DB Load] Loaded room ${code} from DB into memory.`);
        return rawData;
      }
    } catch (e) {
      console.warn(`[RoomManager DB Load Error ${code}]:`, e.message);
    }
    return null;
  }

  async deleteRoomDb(roomCode) {
    if (!roomCode) return;
    try {
      const { isDbConnected, query } = require('./db');
      if (!isDbConnected()) return;
      await query('DELETE FROM active_rooms WHERE room_code = ?', [roomCode.trim().toUpperCase()]);
    } catch (e) {
      console.warn(`[RoomManager DB Delete Error ${roomCode}]:`, e.message);
    }
  }

  async getRoomAsync(roomCode) {
    if (!roomCode || typeof roomCode !== 'string') return null;
    const code = roomCode.trim().toUpperCase();
    let room = this.rooms.get(code);
    if (!room) {
      room = await this.loadRoomDb(code);
    }
    if (room) {
      room.lastActivityAt = Date.now();
    }
    return room || null;
  }

  getRoom(roomCode) {
    if (!roomCode || typeof roomCode !== 'string') return null;
    const code = roomCode.trim().toUpperCase();
    const room = this.rooms.get(code);
    if (room) {
      room.lastActivityAt = Date.now();
    }
    return room || null;
  }

  touchActivity(roomCode) {
    const room = this.rooms.get(roomCode);
    if (room) {
      room.lastActivityAt = Date.now();
    }
  }

  joinRoom(roomCode, nickname) {
    const crypto = require('crypto');
    const room = this.getRoom(roomCode);
    if (!room)                                           return { error: 'ROOM_NOT_FOUND' };
    if (room.phase !== 'LOBBY')                          return { error: 'GAME_ALREADY_STARTED' };
    if (room.players.length >= room.settings.maxPlayers) return { error: 'ROOM_FULL' };

    const playerId          = `p_${crypto.randomUUID().substring(0, 8)}`;
    const rawReconnectToken = crypto.randomBytes(32).toString('hex');
    const tokenHash         = crypto.createHash('sha256').update(rawReconnectToken).digest('hex');

    const player = _makePlayer(playerId, nickname || `لاعب_${Math.floor(1000 + Math.random() * 9000)}`, false, tokenHash);

    room.players.push(player);
    room.lastActivityAt = Date.now();
    this.saveRoomDb(room);
    return { room, player, reconnectToken: rawReconnectToken };
  }

  addBot(roomCode) {
    const crypto = require('crypto');
    const room = this.getRoom(roomCode);
    if (!room)                                           return { error: 'ROOM_NOT_FOUND' };
    if (room.phase !== 'LOBBY')                          return { error: 'GAME_ALREADY_STARTED' };
    if (room.players.length >= room.settings.maxPlayers) return { error: 'ROOM_FULL' };

    const botNames = ['بوت سعيد', 'بوت علياء', 'بوت كريم', 'بوت نورة', 'بوت طارق', 'بوت ريم', 'بوت ماجد', 'بوت سارة'];
    const randomName = botNames[Math.floor(Math.random() * botNames.length)];
    
    const playerId = `bot_${crypto.randomUUID().substring(0, 8)}`;
    const player   = _makePlayer(playerId, randomName, false, null, true);
    room.players.push(player);
    room.lastActivityAt = Date.now();
    this.saveRoomDb(room);
    return { room, player };
  }

  removeBot(roomCode, targetBotId = null) {
    const room = this.getRoom(roomCode);
    if (!room)                  return { error: 'ROOM_NOT_FOUND' };
    if (room.phase !== 'LOBBY') return { error: 'GAME_ALREADY_STARTED' };

    let botIndex = -1;
    if (targetBotId) {
      botIndex = room.players.findIndex(p => p.isBot && p.playerId === targetBotId);
    } else {
      botIndex = room.players.map(p => p.isBot).lastIndexOf(true);
    }

    if (botIndex === -1) return { error: 'NO_BOTS_FOUND' };

    room.players.splice(botIndex, 1);
    room.lastActivityAt = Date.now();
    this.saveRoomDb(room);
    return { room };
  }

  bindSocket(roomCode, playerId, socketId) {
    const room   = this.getRoom(roomCode);
    if (!room)   return false;
    const player = room.players.find(p => p.playerId === playerId);
    if (!player) return false;

    player.socketId    = socketId;
    player.isConnected = true;
    if (player.disconnectTimeout) {
      clearTimeout(player.disconnectTimeout);
      player.disconnectTimeout = null;
    }
    room.lastActivityAt = Date.now();
    this.saveRoomDb(room);
    return true;
  }

  reconnectPlayer(roomCode, rawToken, newSocketId) {
    const crypto = require('crypto');
    const room = this.getRoom(roomCode);
    if (!room) return { error: 'ROOM_NOT_FOUND' };

    const tokenHash = crypto.createHash('sha256').update(rawToken || '').digest('hex');
    const player = room.players.find(p => p.reconnectTokenHash === tokenHash || p.reconnectToken === rawToken);
    if (!player) return { error: 'INVALID_RECONNECT_TOKEN' };

    player.socketId    = newSocketId;
    player.isConnected = true;
    if (player.disconnectTimeout) {
      clearTimeout(player.disconnectTimeout);
      player.disconnectTimeout = null;
    }
    room.lastActivityAt = Date.now();
    this.saveRoomDb(room);
    return { room, player };
  }

  leaveRoom(roomCode, playerId) {
    const room = this.getRoom(roomCode);
    if (!room) return { error: 'ROOM_NOT_FOUND' };

    const playerIndex = room.players.findIndex(p => p.playerId === playerId);
    if (playerIndex === -1) return { error: 'PLAYER_NOT_FOUND' };

    const leavingPlayer = room.players[playerIndex];
    if (leavingPlayer.disconnectTimeout) {
      clearTimeout(leavingPlayer.disconnectTimeout);
      leavingPlayer.disconnectTimeout = null;
    }

    room.players.splice(playerIndex, 1);
    console.log(`[Room ${roomCode}] "${leavingPlayer.nickname}" left the room.`);

    // If leaving player was host, pass host to next human player
    if (leavingPlayer.isHost && room.players.length > 0) {
      const nextHuman = room.players.find(p => !p.isBot && p.isConnected) || room.players.find(p => !p.isBot);
      if (nextHuman) {
        nextHuman.isHost = true;
        room.hostPlayerId = nextHuman.playerId;
        console.log(`[Room ${roomCode}] Host reassigned to "${nextHuman.nickname}"`);
      }
    }

    // Check if any human players remain in room
    const remainingHumans = room.players.filter(p => !p.isBot);
    if (remainingHumans.length === 0) {
      console.log(`[RoomManager] Room ${roomCode} destroyed: No human players remain in room.`);
      this.removeRoom(roomCode);
      return { roomDestroyed: true };
    }

    room.lastActivityAt = Date.now();
    return { room, roomDestroyed: false };
  }

  handleDisconnect(roomCode, playerId, onDisconnectExpired) {
    const room   = this.getRoom(roomCode);
    if (!room)   return { roomDestroyed: false };
    const player = room.players.find(p => p.playerId === playerId);
    if (!player) return { roomDestroyed: false };

    player.isConnected = false;
    player.socketId    = null;

    if (player.disconnectTimeout) {
      clearTimeout(player.disconnectTimeout);
      player.disconnectTimeout = null;
    }

    // Check connected human players
    const connectedHumans = room.players.filter(p => !p.isBot && p.isConnected).length;

    // Rule: In LOBBY, if 0 connected human players remain, destroy room immediately
    if (room.phase === 'LOBBY' && connectedHumans === 0) {
      console.log(`[RoomManager] Room ${roomCode} destroyed: All human players left lobby.`);
      this.removeRoom(roomCode);
      return { roomDestroyed: true };
    }

    // Individual player 30s grace window (marks disconnect expired, but does NOT purge room)
    player.disconnectTimeout = setTimeout(() => {
      player.disconnectTimeout = null;
      const activeRoom = this.rooms.get(roomCode);
      if (activeRoom && onDisconnectExpired && !player.isConnected) {
        // Verify player is still in activeRoom.players and still disconnected
        const isPlayerStillInRoom = activeRoom.players.some(p => p.playerId === player.playerId);
        if (isPlayerStillInRoom && !player.isConnected) {
          try {
            onDisconnectExpired(activeRoom, player);
          } catch (e) {
            console.error(`[Room ${roomCode}] Error in onDisconnectExpired:`, e);
          }
        }
      }
    }, 30_000);

    return { roomDestroyed: false };
  }

  removeRoom(roomCode) {
    if (!roomCode) return;
    const code = roomCode.trim().toUpperCase();
    const room = this.rooms.get(code);
    if (room) {
      if (room.phaseTimeout) {
        clearTimeout(room.phaseTimeout);
        room.phaseTimeout = null;
      }
      room.phaseToken = null;
      if (room.timerInterval) {
        clearInterval(room.timerInterval);
        room.timerInterval = null;
      }
      if (room.emptyTimeout) {
        clearTimeout(room.emptyTimeout);
        room.emptyTimeout = null;
      }
      room.phaseCallback = null;
      if (Array.isArray(room.players)) {
        room.players.forEach(p => {
          if (p.disconnectTimeout) {
            clearTimeout(p.disconnectTimeout);
            p.disconnectTimeout = null;
          }
        });
        room.players.length = 0;
      }
      if (room.nightActions) room.nightActions.clear();
      if (room.votes) room.votes.clear();
      this.rooms.delete(code);
      this.deleteRoomDb(code);
      console.log(`[RoomManager] Room ${code} completely destroyed and purged from memory.`);
      if (this.rooms.size === 0) {
        this.stopCleanupWorker();
      }
    }
  }

  /**
   * Safe Timeout-based Garbage Collector.
   * Schedules a single timeout only when active rooms exist. 0 rooms = 0 background timers.
   */
  scheduleCleanup(delayMs = 60_000) {
    if (this.cleanupTimeout) return;
    if (this.rooms.size === 0) return;

    this.cleanupTimeout = setTimeout(() => {
      this.performCleanup();
    }, delayMs);
  }

  performCleanup() {
    this.cleanupTimeout = null;
    const now = Date.now();
    const roomsToDelete = [];

    this.rooms.forEach((room, roomCode) => {
      const connectedHumans = room.players.filter(p => !p.isBot && p.isConnected).length;
      const inactiveMs = now - (room.lastActivityAt || room.createdAt || now);

      // Rule 1: Completely empty room (0 connected human players) inactive for > 10 minutes (preserved per spec)
      if (connectedHumans === 0 && inactiveMs > 10 * 60 * 1000) {
        roomsToDelete.push(roomCode);
      }
      // Rule 2: Game finished (GAME_OVER) with no active humans, or finished > 3 minutes ago
      else if (room.phase === 'GAME_OVER' && (connectedHumans === 0 || inactiveMs > 3 * 60 * 1000)) {
        roomsToDelete.push(roomCode);
      }
      // Rule 3: Completely stale room with no activity for > 15 minutes
      else if (inactiveMs > 15 * 60 * 1000) {
        roomsToDelete.push(roomCode);
      }
    });

    roomsToDelete.forEach(code => this.removeRoom(code));

    if (this.rooms.size > 0) {
      this.scheduleCleanup(60_000);
    }
  }

  stopCleanupWorker() {
    if (this.cleanupTimeout) {
      clearTimeout(this.cleanupTimeout);
      this.cleanupTimeout = null;
    }
  }
}

const { sanitizeInput } = require('./sanitizer');

function _makePlayer(playerId, nickname, isHost, reconnectTokenHash, isBot = false) {
  const cleanNickname = sanitizeInput(nickname, 24) || (isBot ? 'Bot' : 'Player');
  return {
    playerId,
    socketId:             null,
    nickname:             cleanNickname,
    isHost,
    isBot,
    isAlive:              true,
    isConnected:          true,
    isReady:              false,
    reconnectTokenHash,
    role:                 null,
    faction:              null,
    statuses:             new Set(),
    tasks:                [],
    completedTasks:       0,
    nightAction:          null,
    vote:                 null,
    emergencyMeetingUsed: false,
    disconnectTimeout:    null,
  };
}

module.exports = new RoomManager();
