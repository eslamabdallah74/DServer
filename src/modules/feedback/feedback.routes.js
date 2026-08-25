const express = require('express');
const FeedbackRepository = require('./feedback.repository');
const FeedbackService = require('./feedback.service');
const FeedbackController = require('./feedback.controller');
const AuthMiddleware = require('../../middleware/auth.middleware');

const router = express.Router();

const repository = new FeedbackRepository();
const service = new FeedbackService(repository);
const controller = new FeedbackController(service);

// Public submission endpoints for Flutter client
router.post('/direct', (req, res, next) => controller.submitDirectFeedback(req, res, next));
router.post('/rating', (req, res, next) => controller.submitMatchRating(req, res, next));

// Protected Admin routes
router.use(AuthMiddleware.authenticateAdmin);
router.get('/direct', (req, res, next) => controller.listDirectFeedback(req, res, next));
router.delete('/direct/:feedbackId', (req, res, next) => controller.deleteDirectFeedback(req, res, next));
router.get('/ratings', (req, res, next) => controller.listMatchRatings(req, res, next));
router.delete('/ratings/:ratingId', (req, res, next) => controller.deleteMatchRating(req, res, next));

module.exports = router;
