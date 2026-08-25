const ResponseHandler = require('../../core/ResponseHandler');

class FeedbackController {
  constructor(feedbackService) {
    this.feedbackService = feedbackService;
  }

  async submitDirectFeedback(req, res, next) {
    try {
      const result = await this.feedbackService.submitDirectFeedback(req.body);
      return ResponseHandler.created(res, result);
    } catch (err) {
      next(err);
    }
  }

  async listDirectFeedback(req, res, next) {
    try {
      const { category = '', limit = 50, offset = 0 } = req.query;
      const result = await this.feedbackService.listDirectFeedback({ category, limit, offset });
      return ResponseHandler.success(res, result);
    } catch (err) {
      next(err);
    }
  }

  async deleteDirectFeedback(req, res, next) {
    try {
      await this.feedbackService.deleteDirectFeedback(req.params.feedbackId);
      return ResponseHandler.success(res, { success: true, message: 'تم حذف الملاحظة بنجاح.' });
    } catch (err) {
      next(err);
    }
  }

  async submitMatchRating(req, res, next) {
    try {
      const result = await this.feedbackService.submitMatchRating(req.body);
      return ResponseHandler.created(res, result);
    } catch (err) {
      next(err);
    }
  }

  async listMatchRatings(req, res, next) {
    try {
      const { ratingFilter, limit = 50, offset = 0 } = req.query;
      const result = await this.feedbackService.listMatchRatings({ ratingFilter, limit, offset });
      return ResponseHandler.success(res, result);
    } catch (err) {
      next(err);
    }
  }

  async deleteMatchRating(req, res, next) {
    try {
      await this.feedbackService.deleteMatchRating(req.params.ratingId);
      return ResponseHandler.success(res, { success: true, message: 'تم حذف تقييم المباراة بنجاح.' });
    } catch (err) {
      next(err);
    }
  }
}

module.exports = FeedbackController;
