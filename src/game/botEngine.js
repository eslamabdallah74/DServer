const { processNightAction } = require('./nightResolver');
const roomManager          = require('./roomManager');

function handleBotActionsForPhase(room, io) {
  const bots = room.players.filter(p => p.isBot && p.isAlive);
  if (bots.length === 0) return;
  
  const { checkAllReady, broadcastSanitizedRoomSnapshot } = require('./gameEngine');

  const expectedPhase = room.phase;
  const expectedRound = room.round;
  const expectedToken = room.phaseToken;

  if (room.phase === 'NIGHT') {
    bots.forEach(bot => {
      // Simulate thinking time (2 to 5 seconds)
      const delay = Math.floor(Math.random() * 3000) + 2000;
      setTimeout(() => {
        try {
          // Re-verify room, round, phase, token, and bot living state
          const activeRoom = roomManager.getRoom(room.roomCode);
          if (!activeRoom || activeRoom !== room) return;
          if (room.phase !== 'NIGHT' || room.round !== expectedRound || room.phaseToken !== expectedToken) return;
          if (!bot.isAlive) return;

          const livingPlayers = room.players.filter(p => p.isAlive);
          let targetId = null;

          if (bot.faction === 'shadow') {
            // Shadow bots target non-shadow living players
            const nonShadows = livingPlayers.filter(p => p.faction !== 'shadow');
            if (nonShadows.length > 0) {
              targetId = nonShadows[Math.floor(Math.random() * nonShadows.length)].playerId;
            }
          } else if (bot.role === 'doctor') {
            // Doctor saves a living player
            if (livingPlayers.length > 0) {
              targetId = livingPlayers[Math.floor(Math.random() * livingPlayers.length)].playerId;
            }
          } else if (bot.role === 'guard' || bot.role === 'royal_guard' || bot.role === 'knight') {
            // Protectors guard another living player
            const validTargets = livingPlayers.filter(p => p.playerId !== bot.playerId);
            if (validTargets.length > 0) {
              targetId = validTargets[Math.floor(Math.random() * validTargets.length)].playerId;
            }
          } else if (bot.role === 'investigator' || bot.role === 'minister' || bot.role === 'spy') {
            // Investigators check another player
            const validTargets = livingPlayers.filter(p => p.playerId !== bot.playerId);
            if (validTargets.length > 0) {
              targetId = validTargets[Math.floor(Math.random() * validTargets.length)].playerId;
            }
          }

          if (targetId) {
            processNightAction(room, bot.playerId, targetId, io);
          }
          
          bot.isReady = true;
          broadcastSanitizedRoomSnapshot(io, room);
          checkAllReady(room, io);
        } catch (err) {
          console.error(`[Room ${room.roomCode}] Bot Night action error for ${bot.nickname}:`, err);
        }
      }, delay);
    });
  } else if (room.phase === 'VOTING') {
    bots.forEach(bot => {
      // Simulate thinking time (3 to 6 seconds)
      const delay = Math.floor(Math.random() * 3000) + 3000;
      setTimeout(() => {
        try {
          const activeRoom = roomManager.getRoom(room.roomCode);
          if (!activeRoom || activeRoom !== room) return;
          if (room.phase !== 'VOTING' || room.round !== expectedRound || room.phaseToken !== expectedToken) return;
          if (!bot.isAlive) return;

          const livingPlayers = room.players.filter(p => p.isAlive);
          const validTargets  = livingPlayers.filter(p => p.playerId !== bot.playerId);
          
          if (validTargets.length > 0) {
            const target = validTargets[Math.floor(Math.random() * validTargets.length)].playerId;
            
            if (!room.votes.has(bot.playerId)) {
              room.votes.set(bot.playerId, target);
              
              const voteTally = {};
              room.votes.forEach((tid) => {
                voteTally[tid] = (voteTally[tid] || 0) + 1;
              });
              io.to(room.roomCode).emit('s_vote_update', { 
                voteTally, 
                totalVoters: room.players.filter(p => p.isAlive).length 
              });
            }
          }
          
          bot.isReady = true;
          broadcastSanitizedRoomSnapshot(io, room);
          checkAllReady(room, io);
        } catch (err) {
          console.error(`[Room ${room.roomCode}] Bot Voting action error for ${bot.nickname}:`, err);
        }
      }, delay);
    });
  } else {
    // For DAY, ROLE_REVEAL, MORNING, ELIMINATION, etc.
    bots.forEach(bot => {
      const delay = Math.floor(Math.random() * 2000) + 1000;
      setTimeout(() => {
        try {
          const activeRoom = roomManager.getRoom(room.roomCode);
          if (!activeRoom || activeRoom !== room) return;
          if (room.phase !== expectedPhase || room.phaseToken !== expectedToken) return;
          if (!bot.isAlive) return;

          bot.isReady = true;
          broadcastSanitizedRoomSnapshot(io, room);
          checkAllReady(room, io);
        } catch (err) {
          console.error(`[Room ${room.roomCode}] Bot ready error for ${bot.nickname}:`, err);
        }
      }, delay);
    });
  }
}

module.exports = { handleBotActionsForPhase };
