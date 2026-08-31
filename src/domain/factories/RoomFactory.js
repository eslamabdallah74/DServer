const Room = require('../entities/Room');
const PlayerFactory = require('./PlayerFactory');

class RoomFactory {
  static generateRoomCode(existingRooms) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    do {
      code = Array.from({ length: 6 }, () => chars[Math.floor(Math.random() * chars.length)]).join('');
    } while (existingRooms.has(code));
    return code;
  }

  static createRoom(existingRooms, hostNickname, customSettings = {}) {
    const roomCode = this.generateRoomCode(existingRooms);
    const { player: hostPlayer, reconnectToken } = PlayerFactory.createPlayer(hostNickname, true);

    const room = new Room(roomCode, hostPlayer.playerId, customSettings);
    room.players.push(hostPlayer);

    return { room, hostPlayer, reconnectToken };
  }
}

module.exports = RoomFactory;
