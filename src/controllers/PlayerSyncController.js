const PlayerProfileRepository = require('../repositories/PlayerProfileRepository');

class PlayerSyncController {
  static async sync(req, res) {
    try {
      const payload = req.body || {};
      if (!payload.playerId) {
        return res.status(400).json({ success: false, message: 'playerId is required' });
      }

      const profile = await PlayerProfileRepository.syncProfile(payload);
      if (!profile) {
        return res.status(500).json({ success: false, message: 'Failed to sync player profile' });
      }

      return res.status(200).json({
        success: true,
        message: 'Player profile saved successfully',
        profile,
      });
    } catch (err) {
      console.error('[PlayerSyncController] sync error:', err.message);
      return res.status(500).json({ success: false, message: err.message });
    }
  }

  static async getPlayer(req, res) {
    try {
      const { id } = req.params;
      const profile = await PlayerProfileRepository.findById(id);
      if (!profile) {
        return res.status(404).json({ success: false, message: 'Player not found' });
      }

      return res.status(200).json({ success: true, profile });
    } catch (err) {
      console.error('[PlayerSyncController] getPlayer error:', err.message);
      return res.status(500).json({ success: false, message: err.message });
    }
  }
}

module.exports = PlayerSyncController;
