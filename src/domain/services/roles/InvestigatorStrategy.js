const BaseRoleStrategy = require('./BaseRoleStrategy');
const RoleId = require('../../enums/RoleId');
const Faction = require('../../enums/Faction');

class InvestigatorStrategy extends BaseRoleStrategy {
  constructor() {
    super(RoleId.INVESTIGATOR, 70, Faction.KINGDOM);
  }

  execute(room, action, actor, target, context) {
    if (actor.statuses.has('BLOCKED')) return;
    if (!target) return;

    const isShadow = (target.faction === 'shadow');
    const resultText = isShadow ? 'مشتبه به ينتمي لقوى الظلام 👿' : 'مواطن بريء ينتمي للمملكة 🛡️';

    context.results[actor.playerId] = {
      success: true,
      message: `نتيجة التحقيق عن ${target.nickname}: ${resultText}`,
    };
  }
}

module.exports = InvestigatorStrategy;
