class BaseRoleStrategy {
  constructor(roleId, priorityStage, faction) {
    this.roleId = roleId;
    this.priorityStage = priorityStage;
    this.faction = faction;
  }

  execute(room, action, actor, target, context) {
    throw new Error('Abstract method execute() must be implemented');
  }
}

module.exports = BaseRoleStrategy;
