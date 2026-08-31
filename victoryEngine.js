/**
 * victoryEngine.js
 * Tiered Victory Evaluator — v2 (fully aligned with offline victory engine)
 */

const ROLE_FACTION = {
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
  assassin:     'shadow',
  saboteur:     'shadow',
  poisoner:     'shadow',
  spy:          'shadow',
  slasher:      'shadow',
  impersonator: 'shadow',
  forger:       'shadow',
  shadow_leader:'shadow',
  trickster:    'neutral',
  hermit:       'neutral',
};

function evaluateVictory(room) {
  const players = room.players;

  // Guard: don't decide victory before round 1 starts
  if (!room.round || room.round < 1) {
    return { isDecided: false };
  }

  const getFaction = p => p.faction || ROLE_FACTION[p.role] || 'kingdom';

  const livingKingdom = players.filter(p => p.isAlive && getFaction(p) === 'kingdom');
  const livingShadows = players.filter(p => p.isAlive && getFaction(p) === 'shadow');

  // ── Tier 1: Trickster Solo Win ────────────────────────────────────────────
  if (
    room.lastEliminatedPlayer &&
    room.lastEliminatedPlayer.role === 'trickster' &&
    room.lastEliminatedCause === 'VOTE'
  ) {
    return {
      isDecided: true,
      kind: 'SOLO',
      winnerIds: [room.lastEliminatedPlayer.playerId],
      ruleId: 'trickster_voted_out',
      reason: 'فاز المخادع — سقط في تصويت النهار!',
    };
  }

  // ── King Succession Check ──────────────────────────────────────────────
  const livingKing = players.find(p => p.isAlive && p.role === 'king');
  const livingCrownPrince = players.find(p => p.isAlive && p.role === 'crown_prince');

  if (!livingKing && livingCrownPrince) {
    livingCrownPrince.role = 'king';
    room.vacantThrone = false;
  } else if (!livingKing && !livingCrownPrince) {
    room.vacantThrone = true;
  }

  // ── Tier 2: Vacant Throne — Shadow Auto-Win ───────────────────────────────
  if (room.settings.kingMustSurvive && room.vacantThrone === true) {
    return {
      isDecided: true,
      kind: 'FACTION',
      faction: 'SHADOW',
      ruleId: 'king_must_survive',
      reason: 'سقط الملك وخلا العرش من وريث شرعي — انتصرت قوى الظلام!',
    };
  }

  // ── Tier 3: Kingdom Wins — All Shadows Dead ───────────────────────────────
  if (livingShadows.length === 0) {
    return {
      isDecided: true,
      kind: 'FACTION',
      faction: 'KINGDOM',
      ruleId: 'shadows_eliminated',
      reason: 'تم القضاء على جميع الظلال — نصرت المملكة!',
    };
  }

  // ── Tier 3b: Kingdom Wins — 100% Tasks Completed ─────────────────────────
  if (room.taskProgress >= 1.0) {
    return {
      isDecided: true,
      kind: 'FACTION',
      faction: 'KINGDOM',
      ruleId: 'tasks_completed',
      reason: 'أنجزت المملكة 100٪ من مهامها — النصر للمملكة!',
    };
  }

  // ── Tier 4: Shadow Wins — Outnumber Kingdom ───────────────────────────────
  if (livingShadows.length > livingKingdom.length) {
    return {
      isDecided: true,
      kind: 'FACTION',
      faction: 'SHADOW',
      ruleId: 'shadows_outnumber',
      reason: 'تفوق عدد الظلال على المملكة — النصر للظلام!',
    };
  }

  // ── Tier 4b: Shadow Wins — Parity with no protection ────────────────────
  if (livingShadows.length === livingKingdom.length && livingKingdom.length > 0) {
    const hasProtector = livingKingdom.some(
      p => p.role === 'doctor' || p.role === 'guard' || p.role === 'royal_guard'
    );
    if (!hasProtector) {
      return {
        isDecided: true,
        kind: 'FACTION',
        faction: 'SHADOW',
        ruleId: 'shadows_break_parity',
        reason: 'تعادل فريق الظلال مع المملكة وانعدمت الحماية — انتصر الظلام!',
      };
    }
  }

  return { isDecided: false };
}

module.exports = { evaluateVictory };
