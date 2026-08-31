const BaseRoleStrategy = require('./BaseRoleStrategy');
const RoleId = require('../../enums/RoleId');
const Faction = require('../../enums/Faction');

class GuardStrategy extends BaseRoleStrategy {
  constructor() {
    super(RoleId.GUARD, 30, Faction.KINGDOM);
  }

  execute(room, action, actor, target, context) {
    if (actor.statuses.has('BLOCKED')) return;
    if (!target) return;

    if (!context.activeProtections.has(target.playerId)) {
      context.activeProtections.set(target.playerId, []);
    }
    context.activeProtections.get(target.playerId).push({ protectorId: actor.playerId, type: 'GUARD' });
    context.results[actor.playerId] = {
      success: true,
      message: `قمت بحماية ${target.nickname} الليلة.`,
    };
  }
}

module.exports = GuardStrategy;
