class PlayerService {
  constructor(playerRepository) {
    this.playerRepository = playerRepository;
  }

  async syncProfile(data) {
    if (!data.playerId || !data.name) {
      throw new Error('playerId and name are required');
    }
    return this.playerRepository.upsertPlayer(data);
  }

  async getPlayer(playerId) {
    return this.playerRepository.findById(playerId);
  }

  async listPlayers(options) {
    return this.playerRepository.listPlayers(options);
  }

  async updatePlayer(playerId, updatesData) {
    return this.playerRepository.updatePlayer(playerId, updatesData);
  }

  async deletePlayer(playerId) {
    return this.playerRepository.deletePlayer(playerId);
  }
}

module.exports = PlayerService;
