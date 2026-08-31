const BaseRoleStrategy = require('./BaseRoleStrategy');
const RoleId = require('../../enums/RoleId');
const Faction = require('../../enums/Faction');

class AssassinStrategy extends BaseRoleStrategy {
  constructor() {
    super(RoleId.ASSASSIN, 60, Faction.SHADOW);
  }

  execute(room, action, actor, target, context) {
    if (actor.statuses.has('BLOCKED')) return;
    if (!target) return;

    context.pendingKills.push({ attackerId: actor.playerId, victimId: target.playerId });
    context.results[actor.playerId] = {
      success: true,
      message: `تم توجيه هجوم الظل نحو ${target.nickname}.`,
    };
  }
}

module.exports = AssassinStrategy;
