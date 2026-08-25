const ResponseHandler = require('../../core/ResponseHandler');

class PlayerController {
  constructor(playerService) {
    this.playerService = playerService;
  }

  async syncProfile(req, res, next) {
    try {
      const player = await this.playerService.syncProfile(req.body);
      return ResponseHandler.success(res, { success: true, player });
    } catch (err) {
      next(err);
    }
  }

  async listPlayers(req, res, next) {
    try {
      const { search = '', limit = 50, offset = 0 } = req.query;
      const result = await this.playerService.listPlayers({ search, limit, offset });
      return ResponseHandler.success(res, result);
    } catch (err) {
      next(err);
    }
  }

  async getPlayer(req, res, next) {
    try {
      const player = await this.playerService.getPlayer(req.params.playerId);
      if (!player) {
        return ResponseHandler.notFound(res, 'اللاعب غير موجود.', 'PLAYER_NOT_FOUND');
      }
      return ResponseHandler.success(res, { player });
    } catch (err) {
      next(err);
    }
  }

  async updatePlayer(req, res, next) {
    try {
      const updated = await this.playerService.updatePlayer(req.params.playerId, req.body || {});
      return ResponseHandler.success(res, { success: true, player: updated });
    } catch (err) {
      next(err);
    }
  }

  async deletePlayer(req, res, next) {
    try {
      await this.playerService.deletePlayer(req.params.playerId);
      return ResponseHandler.success(res, { success: true, message: 'تم حذف بيانات اللاعب بنجاح.' });
    } catch (err) {
      next(err);
    }
  }
}

module.exports = PlayerController;
