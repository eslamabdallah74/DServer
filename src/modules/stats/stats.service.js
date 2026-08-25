const Database = require('../../core/Database');

class StatsService {
  get db() {
    return Database.getInstance().getPool();
  }

  async getOverviewStats() {
    const [playersRow] = await this.db.query('SELECT COUNT(*) as total FROM players');
    const [feedbackRow] = await this.db.query('SELECT COUNT(*) as total FROM direct_feedback');
    const [ratingsRow] = await this.db.query('SELECT COUNT(*) as total, AVG(rating) as avgRating FROM match_ratings');
    const [issuesRow] = await this.db.query('SELECT COUNT(*) as total FROM app_issues');
    const [criticalRow] = await this.db.query("SELECT COUNT(*) as total FROM app_issues WHERE severity = 'critical'");

    return {
      playersCount: playersRow[0]?.total || 0,
      feedbackCount: feedbackRow[0]?.total || 0,
      ratingsCount: ratingsRow[0]?.total || 0,
      averageRating: parseFloat(ratingsRow[0]?.avgRating || 0).toFixed(1),
      issuesCount: issuesRow[0]?.total || 0,
      criticalCount: criticalRow[0]?.total || 0,
    };
  }
}

module.exports = StatsService;
