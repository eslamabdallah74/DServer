const ResponseHandler = require('../../core/ResponseHandler');

class StatsController {
  constructor(statsService) {
    this.statsService = statsService;
  }

  async getOverview(req, res, next) {
    try {
      const stats = await this.statsService.getOverviewStats();
      return ResponseHandler.success(res, stats);
    } catch (err) {
      next(err);
    }
  }
}

module.exports = StatsController;
