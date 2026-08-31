const { sanitizeInput } = require('../../../sanitizer');

class Player {
  constructor({ playerId, nickname, isHost = false, reconnectToken = '', isBot = false }) {
    this.playerId = playerId;
    this.nickname = sanitizeInput(nickname, 24) || (isBot ? 'Bot' : 'Player');
    this.isHost = isHost;
    this.isBot = isBot;
    this.reconnectToken = reconnectToken;
    this.socketId = null;
    this.isAlive = true;
    this.isConnected = true;
    this.isReady = false;
    this.role = null;
    this.faction = null;
    this.statuses = new Set();
    this.tasks = [];
    this.completedTasks = 0;
    this.nightAction = null;
    this.vote = null;
    this.emergencyMeetingUsed = false;
    this.disconnectTimeout = null;
    this.copiedRole = null;
  }

  setSocketId(socketId) {
    this.socketId = socketId;
    this.isConnected = true;
    if (this.disconnectTimeout) {
      clearTimeout(this.disconnectTimeout);
      this.disconnectTimeout = null;
    }
  }

  markDisconnected(timeoutMs = 60000, onExpired) {
    this.isConnected = false;
    this.socketId = null;
    if (this.disconnectTimeout) {
      clearTimeout(this.disconnectTimeout);
    }
    if (onExpired) {
      this.disconnectTimeout = setTimeout(onExpired, timeoutMs);
    }
  }

  cleanTimers() {
    if (this.disconnectTimeout) {
      clearTimeout(this.disconnectTimeout);
      this.disconnectTimeout = null;
    }
  }

  toPublicJSON() {
    return {
      playerId: this.playerId,
      nickname: this.nickname,
      isHost: this.isHost,
      isBot: this.isBot,
      isAlive: this.isAlive,
      isConnected: this.isConnected,
      isReady: this.isReady,
      completedTasks: this.completedTasks,
      role: this.role,
      faction: this.faction,
    };
  }
}

module.exports = Player;
