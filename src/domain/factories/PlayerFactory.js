const { randomUUID } = require('crypto');
const Player = require('../entities/Player');

class PlayerFactory {
  static createPlayer(nickname, isHost = false) {
    const playerId = `p_${randomUUID().substring(0, 8)}`;
    const reconnectToken = `token_${randomUUID()}`;
    return {
      player: new Player({ playerId, nickname, isHost, reconnectToken, isBot: false }),
      reconnectToken,
    };
  }

  static createBot(nickname) {
    const playerId = `bot_${randomUUID().substring(0, 8)}`;
    return new Player({ playerId, nickname, isHost: false, reconnectToken: '', isBot: true });
  }
}

module.exports = PlayerFactory;
