class FeedbackService {
  constructor(feedbackRepository) {
    this.feedbackRepository = feedbackRepository;
  }

  async submitDirectFeedback(data) {
    if (!data.feedbackId || !data.message) {
      throw new Error('feedbackId and message are required');
    }
    return this.feedbackRepository.insertDirectFeedback(data);
  }

  async listDirectFeedback(options) {
    return this.feedbackRepository.listDirectFeedback(options);
  }

  async deleteDirectFeedback(feedbackId) {
    return this.feedbackRepository.deleteDirectFeedback(feedbackId);
  }

  async submitMatchRating(data) {
    if (!data.ratingId || !data.matchId || data.rating === undefined) {
      throw new Error('ratingId, matchId, and rating are required');
    }
    return this.feedbackRepository.insertMatchRating(data);
  }

  async listMatchRatings(options) {
    return this.feedbackRepository.listMatchRatings(options);
  }

  async deleteMatchRating(ratingId) {
    return this.feedbackRepository.deleteMatchRating(ratingId);
  }
}

module.exports = FeedbackService;
