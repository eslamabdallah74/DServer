const Pusher = require('pusher');

const pusher = new Pusher({
  appId: "2190859",
  key: "3f174c9cbe3c9757ce57",
  secret: "07621a4662e103902400",
  cluster: "mt1",
  useTLS: true
});

/**
 * Broadcasts a real-time event to a room's Pusher channel (room-ROOMCODE)
 * @param {string} roomCode 
 * @param {string} eventName 
 * @param {object} data 
 */
function broadcastToRoom(roomCode, eventName, data) {
  if (!roomCode) return;
  const channelName = `room-${roomCode.trim().toUpperCase()}`;
  pusher.trigger(channelName, eventName, data).then(() => {
    console.log(`📡 [PUSHER] Broadcasted "${eventName}" to channel "${channelName}"`);
  }).catch((err) => {
    console.error(`⚠️ [PUSHER] Error broadcasting "${eventName}" to channel "${channelName}":`, err ? (err.message || err) : err);
  });
}

module.exports = {
  pusher,
  broadcastToRoom
};
