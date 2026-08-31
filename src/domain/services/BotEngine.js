class BotEngine {
  static handleBotActionsForPhase(room, io, processNightActionCallback, checkAllReadyCallback, broadcastCallback) {
    const bots = room.players.filter(p => p.isBot && p.isAlive);
    if (bots.length === 0) return;

    const expectedPhase = room.phase;
    const expectedRound = room.round;
    const expectedToken = room.phaseToken;

    if (room.phase === 'NIGHT') {
      bots.forEach(bot => {
        const delay = Math.floor(Math.random() * 3000) + 2000;
        const handle = setTimeout(() => {
          try {
            if (room.phase !== 'NIGHT' || room.round !== expectedRound || room.phaseToken !== expectedToken) return;
            if (!bot.isAlive) return;

            const livingPlayers = room.players.filter(p => p.isAlive);
            let targetId = null;

            if (bot.faction === 'shadow') {
              const nonShadows = livingPlayers.filter(p => p.faction !== 'shadow');
              if (nonShadows.length > 0) {
                targetId = nonShadows[Math.floor(Math.random() * nonShadows.length)].playerId;
              }
            } else if (bot.role === 'doctor') {
              if (livingPlayers.length > 0) {
                targetId = livingPlayers[Math.floor(Math.random() * livingPlayers.length)].playerId;
              }
            } else if (['guard', 'royal_guard', 'knight', 'investigator', 'minister', 'spy'].includes(bot.role)) {
              const validTargets = livingPlayers.filter(p => p.playerId !== bot.playerId);
              if (validTargets.length > 0) {
                targetId = validTargets[Math.floor(Math.random() * validTargets.length)].playerId;
              }
            }

            if (targetId && processNightActionCallback) {
              processNightActionCallback(room, bot.playerId, targetId, io);
            }

            bot.isReady = true;
            if (broadcastCallback) broadcastCallback(io, room);
            if (checkAllReadyCallback) checkAllReadyCallback(room, io);
          } catch (err) {
            console.error(`[BotEngine] Night action error for ${bot.nickname}:`, err.message);
          }
        }, delay);

        room.addBotTimeout(handle);
      });
    } else if (room.phase === 'VOTING') {
      bots.forEach(bot => {
        const delay = Math.floor(Math.random() * 3000) + 3000;
        const handle = setTimeout(() => {
          try {
            if (room.phase !== 'VOTING' || room.round !== expectedRound || room.phaseToken !== expectedToken) return;
            if (!bot.isAlive) return;

            const livingPlayers = room.players.filter(p => p.isAlive);
            const validTargets = livingPlayers.filter(p => p.playerId !== bot.playerId);

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
                  totalVoters: room.players.filter(p => p.isAlive).length,
                });
              }
            }

            bot.isReady = true;
            if (broadcastCallback) broadcastCallback(io, room);
            if (checkAllReadyCallback) checkAllReadyCallback(room, io);
          } catch (err) {
            console.error(`[BotEngine] Voting action error for ${bot.nickname}:`, err.message);
          }
        }, delay);

        room.addBotTimeout(handle);
      });
    } else {
      bots.forEach(bot => {
        const delay = Math.floor(Math.random() * 2000) + 1000;
        const handle = setTimeout(() => {
          try {
            if (room.phase !== expectedPhase || room.phaseToken !== expectedToken) return;
            if (!bot.isAlive) return;

            bot.isReady = true;
            if (broadcastCallback) broadcastCallback(io, room);
            if (checkAllReadyCallback) checkAllReadyCallback(room, io);
          } catch (err) {
            console.error(`[BotEngine] Ready action error for ${bot.nickname}:`, err.message);
          }
        }, delay);

        room.addBotTimeout(handle);
      });
    }
  }
}

module.exports = BotEngine;
