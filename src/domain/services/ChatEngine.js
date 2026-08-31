const { sanitizeInput } = require('../../../sanitizer');

class ChatEngine {
  constructor() {
    this.rateLimits = new Map(); // playerId -> Array<timestamp>
  }

  checkRateLimit(playerId, maxMessages = 5, windowMs = 5000) {
    const now = Date.now();
    const timestamps = this.rateLimits.get(playerId) || [];
    const validTimestamps = timestamps.filter(t => now - t < windowMs);

    if (validTimestamps.length >= maxMessages) {
      return false;
    }

    validTimestamps.push(now);
    this.rateLimits.set(playerId, validTimestamps);
    return true;
  }

  processMessage(io, room, player, text) {
    if (!text || typeof text !== 'string') return { error: 'EMPTY_MESSAGE' };

    const cleanText = sanitizeInput(text, 200);
    if (!cleanText) return { error: 'EMPTY_MESSAGE' };

    if (!this.checkRateLimit(player.playerId)) {
      return { error: 'RATE_LIMIT_EXCEEDED' };
    }

    if (player.statuses && player.statuses.has('SILENCED')) {
      return { error: 'PLAYER_SILENCED' };
    }

    const msg = {
      senderId: player.playerId,
      senderName: player.nickname,
      text: cleanText,
      timestamp: Date.now(),
      isAlive: player.isAlive,
    };

    room.chatHistory.push(msg);
    if (room.chatHistory.length > 100) {
      room.chatHistory.shift();
    }

    io.to(room.roomCode).emit('s_chat_message', msg);
    return { success: true, message: msg };
  }
}

module.exports = new ChatEngine();
