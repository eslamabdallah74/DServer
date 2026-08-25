class IssueService {
  constructor(issueRepository) {
    this.issueRepository = issueRepository;
  }

  async logIssue(data) {
    if (!data.issueId || !data.message) {
      throw new Error('issueId and message are required');
    }
    return this.issueRepository.insertIssue(data);
  }

  async listIssues(options) {
    return this.issueRepository.listIssues(options);
  }

  async deleteIssue(issueId) {
    return this.issueRepository.deleteIssue(issueId);
  }

  async clearAll() {
    return this.issueRepository.clearAll();
  }
}

module.exports = IssueService;
