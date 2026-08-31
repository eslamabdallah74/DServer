const { sanitizeInput } = require('./sanitizer');

class ChatEngine {
  constructor() {
    this.rateLimits = new Map(); // socketId -> Array<timestamp>
  }

  checkRateLimit(socketId) {
    const now = Date.now();
    const timestamps = (this.rateLimits.get(socketId) || []).filter(t => now - t < 3000);

    if (timestamps.length >= 4) {
      return false; // Rate limit exceeded (max 4 msgs per 3s)
    }

    timestamps.push(now);
    this.rateLimits.set(socketId, timestamps);
    return true;
  }

  cleanupSocket(socketId) {
    if (socketId) {
      this.rateLimits.delete(socketId);
    }
  }

  processMessage(io, room, player, text) {
    if (!player || !player.socketId) return { error: 'NOT_CONNECTED' };

    // Rate limit check
    if (!this.checkRateLimit(player.socketId)) {
      return { error: 'RATE_LIMIT_EXCEEDED' };
    }

    const cleanText = sanitizeInput(text, 250);
    if (!cleanText) return { error: 'EMPTY_MESSAGE' };

    // Silenced players cannot speak during day/voting phases
    if (player.statuses.has('SILENCED') && (room.phase === 'DISCUSSION' || room.phase === 'VOTING')) {
      return { error: 'PLAYER_SILENCED' };
    }

    if (room.phase === 'ROLE_REVEAL') {
      return { error: 'CHAT_NOT_PERMITTED' };
    }

    // Server-authoritative channel determination
    let channel = 'PUBLIC';

    if (!player.isAlive) {
      channel = 'DEAD';
    } else if (room.phase === 'NIGHT') {
      if (player.faction === 'shadow') {
        channel = 'SHADOW';
      } else {
        return { error: 'CHAT_NOT_PERMITTED' }; // Living Kingdom cannot chat during night
      }
    }

    const chatMsg = {
      messageId: `msg_${Date.now()}_${Math.floor(Math.random() * 1000)}`,
      playerId: player.playerId,
      nickname: player.nickname,
      text: cleanText,
      channel: channel,
      createdAt: Date.now()
    };

    // Push to room history (max 50)
    room.chatHistory.push(chatMsg);
    if (room.chatHistory.length > 50) {
      room.chatHistory.shift();
    }

    // Information Partitioning Broadcast
    if (channel === 'PUBLIC') {
      room.players.forEach(p => {
        if (p.socketId) io.to(p.socketId).emit('s_chat_message', chatMsg);
      });
    } else if (channel === 'SHADOW') {
      room.players.filter(p => p.faction === 'shadow' && p.socketId).forEach(p => {
        io.to(p.socketId).emit('s_chat_message', chatMsg);
      });
    } else if (channel === 'DEAD') {
      room.players.filter(p => !p.isAlive && p.socketId).forEach(p => {
        io.to(p.socketId).emit('s_chat_message', chatMsg);
      });
    }

    return { success: true, message: chatMsg };
  }

  getPermittedHistory(room, player) {
    if (!player) return [];
    return room.chatHistory.filter(msg => {
      if (msg.channel === 'PUBLIC') return true;
      if (msg.channel === 'SHADOW' && player.faction === 'shadow') return true;
      if (msg.channel === 'DEAD' && !player.isAlive) return true;
      return false;
    });
  }
}

module.exports = new ChatEngine();
