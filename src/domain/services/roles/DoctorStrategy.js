const BaseRoleStrategy = require('./BaseRoleStrategy');
const RoleId = require('../../enums/RoleId');
const Faction = require('../../enums/Faction');

class DoctorStrategy extends BaseRoleStrategy {
  constructor() {
    super(RoleId.DOCTOR, 50, Faction.KINGDOM);
  }

  execute(room, action, actor, target, context) {
    if (actor.statuses.has('BLOCKED')) return;
    if (!target) return;

    context.activeHeals.add(target.playerId);
    context.results[actor.playerId] = {
      success: true,
      message: `قمت بعلاج ${target.nickname} الليلة.`,
    };
  }
}

module.exports = DoctorStrategy;
