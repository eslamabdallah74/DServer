/**
 * NightResolver.js
 * 100-Stage Priority Night Action Queue & Outcome Resolver
 */

function resolveNight(room) {
  const players = room.players;
  const actionsMap = room.nightActions || new Map();
  const actions = Array.from(actionsMap.values());

  const results = {};
  const intel = []; // Private feedback messages per playerId
  const killed = [];
  const saved = [];
  const silenced = [];
  const blockedActors = [];
  let recommendation = null;

  // 1. Group actions by role stage priority
  const stageMap = new Map();
  actions.forEach(action => {
    const actor = players.find(p => p.playerId === action.actorPlayerId);
    if (!actor || !actor.isAlive) return;

    const roleId = action.roleId || actor.role;
    const stage = getRoleStagePriority(roleId);

    if (!stageMap.has(stage)) stageMap.set(stage, []);
    stageMap.get(stage).push({ action, actor, roleId });
  });

  const activeProtections = new Map(); // targetPlayerId -> Array<{ protectorId, type }>
  const pendingKills = []; // Array<{ attackerId, victimId }>
  const activeHeals = new Set(); // Set<victimId>
  const activePoison = new Set(); // Set<victimId>

  // 2. Stage-by-Stage Processing
  for (let stage = 10; stage <= 100; stage += 10) {
    const bucket = stageMap.get(stage) || [];

    // Stage 10: Block (Saboteur) & Mark (Shadow Leader)
    if (stage === 10) {
      bucket.forEach(({ action, actor, roleId }) => {
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (!target) return;

        if (roleId === 'saboteur') {
          target.statuses.add('BLOCKED');
          blockedActors.push(target);
          results[actor.playerId] = { success: true, message: `تم تعطيل قدرة ${target.nickname} بنجاح.` };
        } else if (roleId === 'shadow_leader') {
          recommendation = target.playerId;
          results[actor.playerId] = { success: true, message: `تم اقتراح استهداف ${target.nickname}.` };
        }
      });
    }

    // Stage 20: Copy (Impersonator)
    if (stage === 20) {
      bucket.forEach(({ action, actor }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (target && target.role) {
          actor.copiedRole = target.role;
          results[actor.playerId] = { success: true, message: `تم نسخ قدرة ${target.nickname}!` };
        }
      });
    }

    // Stage 30: Protect (Guard / Royal Guard / Knight)
    if (stage === 30) {
      // Royal Guard passive auto-shield for King
      const kingPlayer = players.find(p => p.isAlive && p.role === 'king');
      const royalGuardPlayer = players.find(p => p.isAlive && p.role === 'royal_guard');
      if (kingPlayer && royalGuardPlayer) {
        if (!activeProtections.has(kingPlayer.playerId)) activeProtections.set(kingPlayer.playerId, []);
        activeProtections.get(kingPlayer.playerId).push({ protectorId: royalGuardPlayer.playerId, type: 'ROYAL_GUARD' });
      }

      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (!target) return;

        if (!activeProtections.has(target.playerId)) activeProtections.set(target.playerId, []);

        if (roleId === 'guard') {
          activeProtections.get(target.playerId).push({ protectorId: actor.playerId, type: 'GUARD' });
          results[actor.playerId] = { success: true, message: `قمت بحماية ${target.nickname} الليلة.` };
        } else if (roleId === 'knight') {
          activeProtections.get(target.playerId).push({ protectorId: actor.playerId, type: 'KNIGHT' });
          results[actor.playerId] = { success: true, message: `قمت بإعلان الفدية لحماية ${target.nickname}.` };
        }
      });
    }

    // Stage 40: Silence (Wizard)
    if (stage === 40) {
      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (target && roleId === 'wizard') {
          target.statuses.add('SILENCED');
          silenced.push(target);
          results[actor.playerId] = { success: true, message: `تم إسكات ${target.nickname} للغد.` };
        }
      });
    }

    // Stage 50: Poison (Poisoner)
    if (stage === 50) {
      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (target && roleId === 'poisoner') {
          activePoison.add(target.playerId);
          results[actor.playerId] = { success: true, message: `تم تسميم ${target.nickname}.` };
        }
      });
    }

    // Stage 60: Inspect & Eavesdrop (Investigator / Minister / Spy / Messenger / Forger)
    if (stage === 60) {
      // 1st pass: Forger frames target
      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (target && roleId === 'forger') {
          target.statuses.add('FRAMED');
        }
      });

      // 2nd pass: Inspections
      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (!target) return;

        if (roleId === 'investigator') {
          const isFramed = target.statuses.has('FRAMED');
          const isShadow = isFramed || target.faction === 'shadow';
          const report = isShadow ? 'انتماء ظلامي (Shadow)' : 'انتماء مملكة (Kingdom)';
          intel.push({ playerId: actor.playerId, text: `نتائج فحص ${target.nickname}: ${report}` });
          results[actor.playerId] = { success: true, message: `تقرير الفحص: ${report}` };
        } else if (roleId === 'minister') {
          const factionName = target.faction === 'shadow' ? 'الظلال' : (target.faction === 'neutral' ? 'المحايدون' : 'المملكة');
          intel.push({ playerId: actor.playerId, text: `تقرير الوزير: ${target.nickname} ينتمي إلى فريق ${factionName}.` });
          results[actor.playerId] = { success: true, message: `تقرير الفريق: ${factionName}` };
        } else if (roleId === 'spy') {
          const visitors = actions.filter(a => a.targetPlayerId === target.playerId).map(a => {
            const visitorPlayer = players.find(p => p.playerId === a.actorPlayerId);
            return visitorPlayer ? visitorPlayer.nickname : 'مجهول';
          });
          const reportText = visitors.length > 0 ? `الزوار لـ ${target.nickname}: ${visitors.join(', ')}` : `لم يزر ${target.nickname} أحد.`;
          intel.push({ playerId: actor.playerId, text: reportText });
          results[actor.playerId] = { success: true, message: reportText };
        }
      });
    }

    // Stage 70: Lethal Attacks (Assassin / Slasher)
    if (stage === 70) {
      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (target && (roleId === 'assassin' || roleId === 'slasher')) {
          pendingKills.push({ attackerId: actor.playerId, victimId: target.playerId });
          results[actor.playerId] = { success: true, message: `تم تنفيذ هجومك على ${target.nickname}.` };
        }
      });
    }

    // Stage 80: Heal & Cleanse (Doctor / Priest)
    if (stage === 80) {
      bucket.forEach(({ action, actor, roleId }) => {
        if (actor.statuses.has('BLOCKED')) return;
        const target = players.find(p => p.playerId === action.targetPlayerId);
        if (!target) return;

        if (roleId === 'doctor') {
          activeHeals.add(target.playerId);
          activePoison.delete(target.playerId);
          results[actor.playerId] = { success: true, message: `تم علاج ${target.nickname}.` };
        } else if (roleId === 'priest') {
          target.statuses.delete('SILENCED');
          target.statuses.delete('FRAMED');
          results[actor.playerId] = { success: true, message: `تم تطهير ${target.nickname}.` };
        }
      });
    }

    // Stage 90: Apply Deaths
    if (stage === 90) {
      pendingKills.forEach(({ attackerId, victimId }) => {
        let victim = players.find(p => p.playerId === victimId);
        if (!victim || !victim.isAlive) return;

        const protections = activeProtections.get(victim.playerId) || [];
        const knightProt = protections.find(p => p.type === 'KNIGHT');
        const royalGuardProt = protections.find(p => p.type === 'ROYAL_GUARD');
        const guardProt = protections.find(p => p.type === 'GUARD');
        const hasDoctorHeal = activeHeals.has(victim.playerId);

        if (royalGuardProt) {
          // Royal Guard auto-protects King passively!
          saved.push(victim);
        } else if (guardProt) {
          // Guard protection cancels 1 attack
          saved.push(victim);
        } else if (knightProt) {
          // Knight sacrifices self
          const knightPlayer = players.find(p => p.playerId === knightProt.protectorId);
          if (knightPlayer && knightPlayer.isAlive) {
            knightPlayer.isAlive = false;
            killed.push(knightPlayer);
            saved.push(victim);
          }
        } else if (hasDoctorHeal) {
          saved.push(victim);
        } else {
          victim.isAlive = false;
          killed.push(victim);
        }
      });
    }

    // Stage 100: King Succession Check
    if (stage === 100) {
      const kingPlayer = players.find(p => p.role === 'king');
      if (kingPlayer && !kingPlayer.isAlive) {
        const crownPrince = players.find(p => p.isAlive && p.role === 'crown_prince');
        if (crownPrince) {
          crownPrince.role = 'king';
          intel.push({ playerId: crownPrince.playerId, text: 'استشهد الملك! لقد توليت منصب العرش لحماية المملكة.' });
        } else {
          room.vacantThrone = true;
        }
      }
    }
  }

  // Clear transient status locks for next round
  players.forEach(p => {
    p.statuses.delete('BLOCKED');
  });

  return { results, intel, killed, saved, silenced, blockedActors, recommendation };
}

function getRoleStagePriority(roleId) {
  switch (roleId) {
    case 'saboteur':
    case 'shadow_leader': return 10;
    case 'impersonator': return 20;
    case 'guard':
    case 'royal_guard':
    case 'knight': return 30;
    case 'wizard': return 40;
    case 'poisoner': return 50;
    case 'investigator':
    case 'minister':
    case 'spy':
    case 'messenger':
    case 'forger': return 60;
    case 'assassin':
    case 'slasher': return 70;
    case 'doctor':
    case 'priest': return 80;
    default: return 90;
  }
}

function processNightAction(room, actorPlayerId, targetPlayerId, io) {
  if (!room || room.phase !== 'NIGHT') return;

  const actor = room.players.find(p => p.playerId === actorPlayerId);
  if (!actor || !actor.isAlive) return;

  room.nightActions.set(actor.playerId, {
    actorPlayerId:  actor.playerId,
    roleId:         actor.role,
    targetPlayerId: targetPlayerId,
    submittedAt:    Date.now(),
  });
}

module.exports = { resolveNight, processNightAction };
