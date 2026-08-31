const RoleId = require('../enums/RoleId');
const Faction = require('../enums/Faction');

const ROLE_FACTION = {
  [RoleId.KING]:          Faction.KINGDOM,
  [RoleId.CROWN_PRINCE]:  Faction.KINGDOM,
  [RoleId.DOCTOR]:        Faction.KINGDOM,
  [RoleId.GUARD]:         Faction.KINGDOM,
  [RoleId.ROYAL_GUARD]:   Faction.KINGDOM,
  [RoleId.KNIGHT]:        Faction.KINGDOM,
  [RoleId.MINISTER]:      Faction.KINGDOM,
  [RoleId.WIZARD]:        Faction.KINGDOM,
  [RoleId.INVESTIGATOR]:  Faction.KINGDOM,
  [RoleId.MESSENGER]:     Faction.KINGDOM,
  [RoleId.PRIEST]:        Faction.KINGDOM,
  [RoleId.CITIZEN]:       Faction.KINGDOM,

  [RoleId.ASSASSIN]:      Faction.SHADOW,
  [RoleId.SABOTEUR]:      Faction.SHADOW,
  [RoleId.POISONER]:      Faction.SHADOW,
  [RoleId.SPY]:           Faction.SHADOW,
  [RoleId.SLASHER]:       Faction.SHADOW,
  [RoleId.IMPERSONATOR]:  Faction.SHADOW,
  [RoleId.FORGER]:        Faction.SHADOW,
  [RoleId.SHADOW_LEADER]: Faction.SHADOW,

  [RoleId.TRICKSTER]:     Faction.NEUTRAL,
  [RoleId.HERMIT]:        Faction.NEUTRAL,
};

class RoleAssigner {
  static getCompositionPool(count) {
    switch (count) {
      case 2:  return [RoleId.KING, RoleId.ASSASSIN];
      case 3:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.ASSASSIN];
      case 4:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.ASSASSIN, RoleId.CITIZEN];
      case 5:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.ASSASSIN];
      case 6:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.GUARD, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.TRICKSTER];
      case 7:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.TRICKSTER];
      case 8:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.INVESTIGATOR, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.TRICKSTER];
      case 9:  return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.INVESTIGATOR, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.POISONER, RoleId.TRICKSTER];
      case 10: return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.INVESTIGATOR, RoleId.ROYAL_GUARD, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.POISONER, RoleId.TRICKSTER];
      case 11: return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.INVESTIGATOR, RoleId.ROYAL_GUARD, RoleId.KNIGHT, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.POISONER, RoleId.TRICKSTER];
      case 12: return [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.INVESTIGATOR, RoleId.ROYAL_GUARD, RoleId.KNIGHT, RoleId.MINISTER, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.POISONER, RoleId.TRICKSTER];
      default: {
        const base = [RoleId.KING, RoleId.CROWN_PRINCE, RoleId.DOCTOR, RoleId.GUARD, RoleId.INVESTIGATOR, RoleId.ROYAL_GUARD, RoleId.KNIGHT, RoleId.MINISTER, RoleId.SPY, RoleId.PRIEST, RoleId.ASSASSIN, RoleId.SABOTEUR, RoleId.POISONER, RoleId.WIZARD, RoleId.SLASHER, RoleId.TRICKSTER];
        while (base.length < count) base.push(RoleId.CITIZEN);
        return base.slice(0, count);
      }
    }
  }

  static assignRoles(room) {
    const count = room.players.length;
    const pool = this.getCompositionPool(count);

    // Fisher-Yates Shuffle
    for (let i = pool.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [pool[i], pool[j]] = [pool[j], pool[i]];
    }

    room.players.forEach((player, idx) => {
      player.role = pool[idx];
      player.faction = ROLE_FACTION[player.role] || Faction.KINGDOM;
      player.isAlive = true;
      player.statuses = new Set();
    });
  }

  static getFaction(role) {
    return ROLE_FACTION[role] || Faction.KINGDOM;
  }
}

module.exports = RoleAssigner;
