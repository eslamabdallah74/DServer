const roomManager = require('../../roomManager');
const chatEngine = require('../domain/services/ChatEngine');
const { assignRoles, startPhase, advanceMatchLoop, broadcastSanitizedRoomSnapshot, checkAllReady } = require('../../gameEngine');
const { processNightAction } = require('../../nightResolver');
const { sanitizeInput, isValidRoomCode, isValidPlayerId } = require('../../sanitizer');

class SocketHandler {
  static registerSocketEvents(io) {
    io.on('connection', (socket) => {
      console.log(`[+] Socket connected: ${socket.id}`);

      // ── Create Room ────────────────────────────────────────────────────────
      socket.on('c_create_room', ({ nickname, settings } = {}) => {
        try {
          const hostNickname = sanitizeInput(nickname, 24) || 'المضيف';
          const { room, hostPlayer, reconnectToken } = roomManager.createRoom(hostNickname, settings);

          hostPlayer.socketId = socket.id;
          socket.join(room.roomCode);

          socket.emit('s_create_room_response', {
            success: true,
            roomCode: room.roomCode,
            playerId: hostPlayer.playerId,
            reconnectToken,
          });

          broadcastSanitizedRoomSnapshot(io, room);
        } catch (err) {
          console.error('[SocketHandler] c_create_room error:', err.message);
          socket.emit('s_error', { code: 'CREATE_ROOM_FAILED', message: 'فشل إنشاء الغرفة' });
        }
      });

      // ── Join Room ──────────────────────────────────────────────────────────
      socket.on('c_join_room', ({ roomCode, nickname } = {}) => {
        try {
          if (!isValidRoomCode(roomCode)) {
            return socket.emit('s_join_room_response', { success: false, error: 'INVALID_ROOM_CODE', message: 'رمز الغرفة غير صحيح' });
          }

          const cleanNickname = sanitizeInput(nickname, 24) || 'لاعب';
          const res = roomManager.joinRoom(roomCode, cleanNickname);

          if (res.error) {
            const errorMessages = {
              ROOM_NOT_FOUND: 'الغرفة غير موجودة',
              ROOM_FULL: 'الغرفة ممتلئة',
              GAME_ALREADY_STARTED: 'المباراة بدأت بالفعل',
            };
            return socket.emit('s_join_room_response', {
              success: false,
              error: res.error,
              message: errorMessages[res.error] || 'تعذر الانضمام للغرفة',
            });
          }

          const { room, player, reconnectToken } = res;
          player.socketId = socket.id;
          socket.join(room.roomCode);

          socket.emit('s_join_room_response', {
            success: true,
            roomCode: room.roomCode,
            playerId: player.playerId,
            reconnectToken,
          });

          broadcastSanitizedRoomSnapshot(io, room);
        } catch (err) {
          console.error('[SocketHandler] c_join_room error:', err.message);
          socket.emit('s_error', { code: 'JOIN_ROOM_FAILED', message: 'فشل الانضمام للغرفة' });
        }
      });

      // ── Reconnect ──────────────────────────────────────────────────────────
      socket.on('c_reconnect', ({ roomCode, playerId, reconnectToken } = {}) => {
        try {
          const res = roomManager.reconnectPlayer(roomCode, playerId, reconnectToken, socket.id);
          if (res.error) {
            return socket.emit('s_reconnect_response', { success: false, error: res.error });
          }

          socket.join(res.room.roomCode);
          socket.emit('s_reconnect_response', { success: true, roomCode: res.room.roomCode, playerId: res.player.playerId });
          broadcastSanitizedRoomSnapshot(io, res.room);
        } catch (err) {
          console.error('[SocketHandler] c_reconnect error:', err.message);
        }
      });

      // ── Start Game ─────────────────────────────────────────────────────────
      socket.on('c_start_game', ({ roomCode, playerId } = {}) => {
        try {
          const room = roomManager.getRoom(roomCode);
          if (!room || room.hostPlayerId !== playerId || room.phase !== 'LOBBY') return;

          assignRoles(room);
          startPhase(room, 'ROLE_REVEAL', io);
        } catch (err) {
          console.error('[SocketHandler] c_start_game error:', err.message);
        }
      });

      // ── Night Action ───────────────────────────────────────────────────────
      socket.on('c_night_action', ({ roomCode, playerId, targetPlayerId } = {}) => {
        try {
          const room = roomManager.getRoom(roomCode);
          if (!room || room.phase !== 'NIGHT') return;

          processNightAction(room, playerId, targetPlayerId, io);
          const player = room.players.find(p => p.playerId === playerId);
          if (player) player.isReady = true;

          broadcastSanitizedRoomSnapshot(io, room);
          checkAllReady(room, io);
        } catch (err) {
          console.error('[SocketHandler] c_night_action error:', err.message);
        }
      });

      // ── Submit Vote ────────────────────────────────────────────────────────
      socket.on('c_submit_vote', ({ roomCode, voterPlayerId, targetPlayerId } = {}) => {
        try {
          const room = roomManager.getRoom(roomCode);
          if (!room || room.phase !== 'VOTING') return;

          const voter = room.players.find(p => p.playerId === voterPlayerId);
          if (!voter || !voter.isAlive || voter.statuses.has('BLOCKED')) return;

          room.votes.set(voterPlayerId, targetPlayerId || 'SKIP');
          voter.isReady = true;

          const voteTally = {};
          room.votes.forEach((tid) => {
            voteTally[tid] = (voteTally[tid] || 0) + 1;
          });

          io.to(room.roomCode).emit('s_vote_update', { voteTally, totalVoters: room.players.filter(p => p.isAlive).length });
          broadcastSanitizedRoomSnapshot(io, room);
          checkAllReady(room, io);
        } catch (err) {
          console.error('[SocketHandler] c_submit_vote error:', err.message);
        }
      });

      // ── Chat ───────────────────────────────────────────────────────────────
      socket.on('c_send_chat', ({ roomCode, playerId, text } = {}) => {
        try {
          const room = roomManager.getRoom(roomCode);
          if (!room) return;

          const player = room.players.find(p => p.playerId === playerId);
          if (!player) return;

          const result = chatEngine.processMessage(io, room, player, text);
          if (result.error) {
            socket.emit('s_error', { code: result.error, message: 'تعذر إرسال الرسالة' });
          }
        } catch (err) {
          console.error('[SocketHandler] c_send_chat error:', err.message);
        }
      });

      // ── Leave Room ─────────────────────────────────────────────────────────
      socket.on('c_leave_room', ({ roomCode, playerId } = {}) => {
        try {
          const res = roomManager.leaveRoom(roomCode, playerId);
          socket.leave(roomCode);
          if (!res.error && !res.roomDestroyed) {
            broadcastSanitizedRoomSnapshot(io, res.room);
          }
        } catch (err) {
          console.error('[SocketHandler] c_leave_room error:', err.message);
        }
      });

      // ── Disconnect ─────────────────────────────────────────────────────────
      socket.on('disconnect', (reason) => {
        console.log(`[-] Socket disconnected: ${socket.id} (${reason})`);
        roomManager.rooms.forEach((room, roomCode) => {
          const player = room.players.find(p => p.socketId === socket.id);
          if (player) {
            const res = roomManager.handleDisconnect(roomCode, player.playerId, (r, p) => {
              broadcastSanitizedRoomSnapshot(io, r);
            });
            if (!res.roomDestroyed) {
              broadcastSanitizedRoomSnapshot(io, room);
            }
          }
        });
      });
    });
  }
}

module.exports = SocketHandler;
