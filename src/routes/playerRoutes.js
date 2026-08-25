const express = require('express');
const {
  syncPlayerProfile,
  getPlayerById,
  listPlayers,
  updatePlayer,
  deletePlayer,
} = require('../services/playerService');
const { authenticateAdmin } = require('../middleware/auth');

const router = express.Router();

// ── Client Sync Endpoint (Unprotected so Flutter app can send player profile) ──
router.post('/sync', async (req, res, next) => {
  try {
    const player = await syncPlayerProfile(req.body);
    return res.json({ success: true, player });
  } catch (err) {
    next(err);
  }
});

// ── Admin Protected Endpoints ─────────────────────────────────────────────
router.use(authenticateAdmin);

// GET /api/players
router.get('/', async (req, res, next) => {
  try {
    const { search = '', limit = 50, offset = 0 } = req.query;
    const result = await listPlayers({ search, limit, offset });
    return res.json(result);
  } catch (err) {
    next(err);
  }
});

// GET /api/players/:playerId
router.get('/:playerId', async (req, res, next) => {
  try {
    const player = await getPlayerById(req.params.playerId);
    if (!player) {
      return res.status(404).json({ error: 'NOT_FOUND', message: 'اللاعب غير موجود.' });
    }
    return res.json({ player });
  } catch (err) {
    next(err);
  }
});

// PUT /api/players/:playerId
router.put('/:playerId', async (req, res, next) => {
  try {
    const updated = await updatePlayer(req.params.playerId, req.body || {});
    return res.json({ success: true, player: updated });
  } catch (err) {
    next(err);
  }
});

// DELETE /api/players/:playerId
router.delete('/:playerId', async (req, res, next) => {
  try {
    await deletePlayer(req.params.playerId);
    return res.json({ success: true, message: 'تم حذف بيانات اللاعب بنجاح.' });
  } catch (err) {
    next(err);
  }
});

module.exports = router;
