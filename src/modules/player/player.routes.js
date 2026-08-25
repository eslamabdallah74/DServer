const express = require('express');
const PlayerRepository = require('./player.repository');
const PlayerService = require('./player.service');
const PlayerController = require('./player.controller');
const AuthMiddleware = require('../../middleware/auth.middleware');

const router = express.Router();

const repository = new PlayerRepository();
const service = new PlayerService(repository);
const controller = new PlayerController(service);

// Public sync endpoint for Flutter client
router.post('/sync', (req, res, next) => controller.syncProfile(req, res, next));

// Protected Admin routes
router.use(AuthMiddleware.authenticateAdmin);
router.get('/', (req, res, next) => controller.listPlayers(req, res, next));
router.get('/:playerId', (req, res, next) => controller.getPlayer(req, res, next));
router.put('/:playerId', (req, res, next) => controller.updatePlayer(req, res, next));
router.delete('/:playerId', (req, res, next) => controller.deletePlayer(req, res, next));

module.exports = router;
