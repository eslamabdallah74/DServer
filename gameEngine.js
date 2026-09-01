/**
 * gameEngine.js  — v2 (fully fixed)
 *
 * Responsibilities:
 *   1. Role assignment by player count (2–16 players)
 *   2. Phase timer machine (startPhase)
 *   3. Match loop controller (advanceMatchLoop)
 *   4. Vote tallying & elimination
 *   5. Sanitized per-player snapshot broadcasting
 *   6. Night action eligibility (which roles act at night)
 */

const { resolveNight }   = require('./nightResolver');
const { evaluateVictory } = require('./victoryEngine');

// ─── Role definitions ────────────────────────────────────────────────────────

const ROLE_FACTION = {
  // Kingdom
  king:         'kingdom',
  crown_prince: 'kingdom',
  doctor:       'kingdom',
  guard:        'kingdom',
  royal_guard:  'kingdom',
  knight:       'kingdom',
  minister:     'kingdom',
  wizard:       'kingdom',
  investigator: 'kingdom',
  messenger:    'kingdom',
  priest:       'kingdom',
  citizen:      'kingdom',
  // Shadow
  assassin:     'shadow',
  saboteur:     'shadow',
  poisoner:     'shadow',
  spy:          'shadow',
  slasher:      'shadow',
  impersonator: 'shadow',
  forger:       'shadow',
  shadow_leader:'shadow',
  // Neutral
  trickster:    'neutral',
  hermit:       'neutral',
};

/**
 * Roles that can submit a night action (have an active ability at night).
 * King, Crown Prince, Citizen, Trickster, Hermit have NO night ability.
 */
const NIGHT_ACTIVE_ROLES = new Set([
  'assassin', 'saboteur', 'poisoner', 'wizard', 'slasher',
  'impersonator', 'forger', 'shadow_leader',
  'doctor', 'guard', 'royal_guard', 'knight',
  'investigator', 'minister', 'spy', 'messenger', 'priest',
]);

/**
 * Role composition table by player count.
 * Always includes at least 1 shadow role.
 * Neutral roles are optional supplements.
 */
function getCompositionPool(count) {
  switch (count) {
    case 2:  return ['king', 'assassin'];
    case 3:  return ['king', 'crown_prince', 'assassin'];
    case 4:  return ['king', 'crown_prince', 'assassin', 'citizen'];
    case 5:  return ['king', 'crown_prince', 'doctor', 'guard', 'assassin'];
    case 6:  return ['king', 'crown_prince', 'guard', 'assassin', 'saboteur', 'trickster'];
    case 7:  return ['king', 'crown_prince', 'doctor', 'guard', 'assassin', 'saboteur', 'trickster'];
    case 8:  return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'assassin', 'saboteur', 'trickster'];
    case 9:  return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'assassin', 'saboteur', 'poisoner', 'trickster'];
    case 10: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'assassin', 'saboteur', 'poisoner', 'trickster'];
    case 11: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'assassin', 'saboteur', 'poisoner', 'trickster'];
    case 12: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'assassin', 'saboteur', 'poisoner', 'trickster'];
    case 13: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'assassin', 'saboteur', 'poisoner', 'wizard', 'trickster'];
    case 14: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'assassin', 'saboteur', 'poisoner', 'wizard', 'trickster'];
    case 15: return ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'priest', 'assassin', 'saboteur', 'poisoner', 'wizard', 'trickster'];
    default: {
      const base = ['king', 'crown_prince', 'doctor', 'guard', 'investigator', 'royal_guard', 'knight', 'minister', 'spy', 'priest', 'assassin', 'saboteur', 'poisoner', 'wizard', 'slasher', 'trickster'];
      while (base.length < count) base.push('citizen');
      return base.slice(0, count);
    }
  }
}

function assignRoles(room) {
  const count = room.players.length;
  const pool  = getCompositionPool(count);

  // Fisher-Yates shuffle
  for (let i = pool.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [pool[i], pool[j]] = [pool[j], pool[i]];
  }

  room.players.forEach((player, idx) => {
    player.role            = pool[idx];
    player.faction         = ROLE_FACTION[player.role] || 'kingdom';
    player.isAlive         = true;
    player.statuses        = new Set();
    player.completedTasks  = 0;
    player.nightAction     = null;
    player.vote            = null;
  });

  // Reset match state
  room.round                = 0;
  room.taskProgress         = 0;
  room.vacantThrone         = false;
  room.lastEliminatedPlayer = null;
  room.lastEliminatedCause  = null;
  room.lastNightOutcome     = null;
  room.victoryOutcome       = null;
  room.darknessActive       = false;
}

// ─── Phase timer ─────────────────────────────────────────────────────────────

/**
 * Starts a named phase, sets a timer, and broadcasts an updated snapshot.
 * Also broadcasts countdown ticks every second so clients have a live timer.
 */
function startPhase(io, room, phaseName, durationSeconds, onComplete) {
  // Clear any existing timer or timeout
  if (room.phaseTimeout) {
    clearTimeout(room.phaseTimeout);
    room.phaseTimeout = null;
  }
  if (room.timerInterval) {
    clearInterval(room.timerInterval);
    room.timerInterval = null;
  }

  // Generate unique phase token for double-execution prevention
  const phaseToken = `${phaseName}_${room.round}_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;
  room.phaseToken = phaseToken;

  // Reset ready state for all players
  room.players.forEach(p => { p.isReady = false; });
  room.phaseCallback = onComplete;

  room.phase          = phaseName;
  room.phaseStartedAt = Date.now();
  room.phaseEndsAt    = durationSeconds > 0 ? Date.now() + durationSeconds * 1000 : 0;
  room.eventSequence += 1;

  console.log(`[Room ${room.roomCode}] Phase → ${phaseName} | ${durationSeconds}s | Round ${room.round}`);

  broadcastToRoom(room.roomCode, 's_phase_changed', {
    eventSequence:  room.eventSequence,
    phase:          room.phase,
    phaseStartedAt: room.phaseStartedAt,
    phaseEndsAt:    room.phaseEndsAt,
    round:          room.round,
    serverTime:     Date.now(),
  });

  broadcastSanitizedRoomSnapshot(io, room);
  
  // Trigger bot actions for this phase safely
  try {
    const botEngine = require('./botEngine');
    if (botEngine && typeof botEngine.handleBotActionsForPhase === 'function') {
      botEngine.handleBotActionsForPhase(room, io);
    }
  } catch (err) {
    // botEngine is optional
  }

  if (durationSeconds <= 0) return;

  room.phaseTimeout = setTimeout(() => {
    room.phaseTimeout = null;
    if (room.phaseToken === phaseToken && room.phaseCallback) {
      const cb = room.phaseCallback;
      room.phaseCallback = null;
      try {
        cb();
      } catch (err) {
        console.error(`[Room ${room.roomCode}] Error in phaseCallback (${phaseName}):`, err);
      }
    }
  }, durationSeconds * 1000);
}

function skipPhase(room, io) {
  if (room.phaseTimeout) {
    clearTimeout(room.phaseTimeout);
    room.phaseTimeout = null;
  }
  if (room.timerInterval) {
    clearInterval(room.timerInterval);
    room.timerInterval = null;
  }
  
  room.phaseToken = null;

  if (room.phaseCallback) {
    const cb = room.phaseCallback;
    room.phaseCallback = null;
    console.log(`[Room ${room.roomCode}] Phase ${room.phase} skipped (All ready)`);
    try {
      cb();
    } catch (err) {
      console.error(`[Room ${room.roomCode}] Error in skipPhase callback (${room.phase}):`, err);
    }
  }
}

function checkAllReady(room, io) {
  // Only living players count. Bots are also players.
  const allReady = room.players.every(p => p.isReady || !p.isAlive);
  if (allReady) {
    skipPhase(room, io);
  }
}

function broadcastTimerTick(io, room, remainingSeconds) {
  io.to(room.roomCode).emit('s_timer_tick', {
    phase:            room.phase,
    remainingSeconds,
    phaseEndsAt:      room.phaseEndsAt,
  });
}

// ─── Snapshot broadcaster ─────────────────────────────────────────────────────

function broadcastSanitizedRoomSnapshot(io, room) {
  room.players.forEach(p => {
    if (!p.socketId) return;

    const shadowAllies = p.faction === 'shadow'
      ? room.players
          .filter(a => a.faction === 'shadow' && a.playerId !== p.playerId)
          .map(a => ({ playerId: a.playerId, nickname: a.nickname }))
      : [];

    const snapshot = {
      roomCode:        room.roomCode,
      eventSequence:   room.eventSequence,
      serverTime:      Date.now(),
      phase:           room.phase,
      phaseStartedAt:  room.phaseStartedAt,
      phaseEndsAt:     room.phaseEndsAt,
      taskProgress:    room.taskProgress,
      darknessActive:  room.darknessActive,
      round:           room.round,
      isHost:          p.isHost,
      hasNightAction:  NIGHT_ACTIVE_ROLES.has(p.role),

      myPlayer: {
        playerId:       p.playerId,
        nickname:       p.nickname,
        role:           p.role,
        faction:        p.faction,
        isAlive:        p.isAlive,
        isHost:         p.isHost,
        statuses:       Array.from(p.statuses),
        completedTasks: p.completedTasks,
      },

      shadowAllies,

    io.to(p.socketId).emit('s_room_snapshot', snapshot);
    io.to(p.socketId).emit('room:updated', snapshot);
    io.to(p.socketId).emit('game:state', snapshot);
  });
}

// ─── Match loop ───────────────────────────────────────────────────────────────

function advanceMatchLoop(io, room) {
  const phase = room.phase;

  if (phase === 'ROLE_REVEAL') {
    // ── Night 1 begins ──
    room.round += 1;
    room.nightActions.clear();
    startPhase(io, room, 'NIGHT', room.settings.nightTimerSeconds, () => {
      advanceMatchLoop(io, room);
    });

  } else if (phase === 'NIGHT') {
    // ── Resolve night actions ──
    const outcome = resolveNight(room);
    room.lastNightOutcome = outcome;

    // MORNING: show night results (10s)
    startPhase(io, room, 'MORNING', 10, () => {
      advanceMatchLoop(io, room);
    });

  } else if (phase === 'MORNING') {
    // ── Post-night victory check ──
    const victory = evaluateVictory(room);
    if (victory.isDecided) {
      room.victoryOutcome = victory;
      startPhase(io, room, 'GAME_OVER', 0, null);
      return;
    }

    // ── DAY: discussion + tasks (shared timer) ──
    startPhase(io, room, 'DAY', room.settings.dayTimerSeconds, () => {
      advanceMatchLoop(io, room);
    });

  } else if (phase === 'DAY') {
    // ── VOTING: players choose who to eliminate ──
    room.votes.clear();
    room.lastEliminatedPlayer = null;
    room.lastEliminatedCause  = null;
    startPhase(io, room, 'VOTING', room.settings.votingTimerSeconds, () => {
      advanceMatchLoop(io, room);
    });

  } else if (phase === 'VOTING') {
    // ── Tally votes ──
    tallyVotesAndEliminate(io, room);

  } else if (phase === 'ELIMINATION') {
    // ── Post-elimination victory check ──
    const victory = evaluateVictory(room);
    if (victory.isDecided) {
      room.victoryOutcome = victory;
      startPhase(io, room, 'GAME_OVER', 0, null);
      return;
    }

    // ── Next NIGHT ──
    room.round += 1;
    room.nightActions.clear();
    startPhase(io, room, 'NIGHT', room.settings.nightTimerSeconds, () => {
      advanceMatchLoop(io, room);
    });
  }
}

// ─── Vote tallying ─────────────────────────────────────────────────────────────

function tallyVotesAndEliminate(io, room) {
  const voteCounts = new Map();

  room.votes.forEach((targetId, voterId) => {
    const voter = room.players.find(p => p.playerId === voterId);
    const target = room.players.find(p => p.playerId === targetId);
    if (voter && voter.isAlive && target && target.isAlive) {
      voteCounts.set(targetId, (voteCounts.get(targetId) || 0) + 1);
    }
  });

  let maxVotes   = 0;
  let candidates = [];

  voteCounts.forEach((count, targetId) => {
    if (count > maxVotes) {
      maxVotes   = count;
      candidates = [targetId];
    } else if (count === maxVotes) {
      candidates.push(targetId);
    }
  });

  if (candidates.length === 1 && maxVotes > 0) {
    const eliminated = room.players.find(p => p.playerId === candidates[0]);
    if (eliminated) {
      eliminated.isAlive        = false;
      room.lastEliminatedPlayer = eliminated;
      room.lastEliminatedCause  = 'VOTE';
    }
  } else {
    room.lastEliminatedPlayer = null;
    room.lastEliminatedCause  = 'TIE';
  }

  // Pre-check victory right after elimination
  const victory = evaluateVictory(room);
  if (victory.isDecided) {
    room.victoryOutcome = victory;
  }

  // ELIMINATION phase (8s) to show the result
  startPhase(io, room, 'ELIMINATION', 8, () => {
    advanceMatchLoop(io, room);
  });
}

module.exports = {
  assignRoles,
  startPhase,
  skipPhase,
  advanceMatchLoop,
  broadcastSanitizedRoomSnapshot,
  checkAllReady,
  NIGHT_ACTIVE_ROLES,
};
