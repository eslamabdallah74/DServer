const express = require('express');
const IssueRepository = require('./issue.repository');
const IssueService = require('./issue.service');
const IssueController = require('./issue.controller');
const AuthMiddleware = require('../../middleware/auth.middleware');

const router = express.Router();

const repository = new IssueRepository();
const service = new IssueService(repository);
const controller = new IssueController(service);

// Public issue logging endpoint for Flutter client
router.post('/', (req, res, next) => controller.logIssue(req, res, next));

// Protected Admin routes
router.use(AuthMiddleware.authenticateAdmin);
router.get('/', (req, res, next) => controller.listIssues(req, res, next));
router.delete('/clear-all', (req, res, next) => controller.clearAllIssues(req, res, next));
router.delete('/:issueId', (req, res, next) => controller.deleteIssue(req, res, next));

module.exports = router;
