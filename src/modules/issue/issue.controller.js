const ResponseHandler = require('../../core/ResponseHandler');

class IssueController {
  constructor(issueService) {
    this.issueService = issueService;
  }

  async logIssue(req, res, next) {
    try {
      const result = await this.issueService.logIssue(req.body);
      return ResponseHandler.created(res, result);
    } catch (err) {
      next(err);
    }
  }

  async listIssues(req, res, next) {
    try {
      const { severity = '', page = '', limit = 50, offset = 0 } = req.query;
      const result = await this.issueService.listIssues({ severity, page, limit, offset });
      return ResponseHandler.success(res, result);
    } catch (err) {
      next(err);
    }
  }

  async deleteIssue(req, res, next) {
    try {
      await this.issueService.deleteIssue(req.params.issueId);
      return ResponseHandler.success(res, { success: true, message: 'تم حذف السجل بنجاح.' });
    } catch (err) {
      next(err);
    }
  }

  async clearAllIssues(req, res, next) {
    try {
      await this.issueService.clearAll();
      return ResponseHandler.success(res, { success: true, message: 'تم مسح جميع سجلات الأخطاء.' });
    } catch (err) {
      next(err);
    }
  }
}

module.exports = IssueController;
