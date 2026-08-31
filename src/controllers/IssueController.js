const AppIssueRepository = require('../repositories/AppIssueRepository');

class IssueController {
  static async logIssue(req, res) {
    try {
      const { severity, message, stackTrace, page, deviceInfo } = req.body || {};
      const issueId = await AppIssueRepository.logIssue({ severity, message, stackTrace, page, deviceInfo });

      return res.status(200).json({ success: true, issueId });
    } catch (err) {
      console.error('[IssueController] logIssue error:', err.message);
      return res.status(500).json({ success: false, message: err.message });
    }
  }

  static async getIssues(req, res) {
    try {
      const issues = await AppIssueRepository.getRecentIssues(50);
      return res.status(200).json({ success: true, issues });
    } catch (err) {
      console.error('[IssueController] getIssues error:', err.message);
      return res.status(500).json({ success: false, message: err.message });
    }
  }
}

module.exports = IssueController;
