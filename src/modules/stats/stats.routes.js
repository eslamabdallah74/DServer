const express = require('express');
const StatsService = require('./stats.service');
const StatsController = require('./stats.controller');
const AuthMiddleware = require('../../middleware/auth.middleware');

const router = express.Router();

const service = new StatsService();
const controller = new StatsController(service);

router.use(AuthMiddleware.authenticateAdmin);
router.get('/overview', (req, res, next) => controller.getOverview(req, res, next));

module.exports = router;
