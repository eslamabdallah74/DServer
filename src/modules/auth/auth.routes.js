const express = require('express');
const AuthRepository = require('./auth.repository');
const AuthService = require('./auth.service');
const AuthController = require('./auth.controller');
const AuthMiddleware = require('../../middleware/auth.middleware');

const router = express.Router();

const repository = new AuthRepository();
const service = new AuthService(repository);
const controller = new AuthController(service);

router.post('/login', (req, res, next) => controller.login(req, res, next));
router.get('/me', AuthMiddleware.authenticateAdmin, (req, res, next) => controller.getMe(req, res, next));
router.post('/logout', (req, res) => controller.logout(req, res));

module.exports = router;
