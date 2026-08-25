const BaseRepository = require('../../core/BaseRepository');

class PlayerRepository extends BaseRepository {
  constructor() {
    super('players');
  }

  async upsertPlayer(data) {
    const {
      playerId,
      name,
      coins = 0,
      matchesPlayed = 0,
      gamesWon = 0,
      roleStats = null,
      customization = null,
      deviceInfo = null,
    } = data;

    const sql = `
      INSERT INTO players 
        (player_id, name, coins, matches_played, games_won, role_stats, customization, device_info)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        coins = VALUES(coins),
        matches_played = VALUES(matches_played),
        games_won = VALUES(games_won),
        role_stats = VALUES(role_stats),
        customization = VALUES(customization),
        device_info = VALUES(device_info),
        updated_at = CURRENT_TIMESTAMP
    `;

    await this.execute(sql, [
      playerId,
      name,
      coins,
      matchesPlayed,
      gamesWon,
      roleStats ? JSON.stringify(roleStats) : null,
      customization ? JSON.stringify(customization) : null,
      typeof deviceInfo === 'object' ? JSON.stringify(deviceInfo) : deviceInfo,
    ]);

    return this.findById(playerId);
  }

  async findById(playerId) {
    const rows = await this.query('SELECT * FROM players WHERE player_id = ?', [playerId]);
    if (rows.length === 0) return null;
    return this._mapRowToEntity(rows[0]);
  }

  async listPlayers({ search = '', limit = 50, offset = 0 } = {}) {
    const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
    const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

    let whereClause = '';
    const params = [];

    if (search.trim()) {
      whereClause = 'WHERE name LIKE ? OR player_id LIKE ?';
      const s = `%${search.trim()}%`;
      params.push(s, s);
    }

    const total = await this.count(whereClause, params);

    const selectSql = `
      SELECT * FROM players 
      ${whereClause} 
      ORDER BY updated_at DESC 
      LIMIT ? OFFSET ?
    `;
    params.push(parsedLimit, parsedOffset);
    const rows = await this.query(selectSql, params);

    const players = rows.map(r => this._mapRowToEntity(r));
    return { players, total };
  }

  async updatePlayer(playerId, updatesData) {
    const updates = [];
    const params = [];

    if (updatesData.coins !== undefined) {
      updates.push('coins = ?');
      params.push(updatesData.coins);
    }
    if (updatesData.name !== undefined) {
      updates.push('name = ?');
      params.push(updatesData.name);
    }
    if (updatesData.matchesPlayed !== undefined) {
      updates.push('matches_played = ?');
      params.push(updatesData.matchesPlayed);
    }
    if (updatesData.gamesWon !== undefined) {
      updates.push('games_won = ?');
      params.push(updatesData.gamesWon);
    }

    if (updates.length === 0) return this.findById(playerId);

    params.push(playerId);
    const sql = `UPDATE players SET ${updates.join(', ')}, updated_at = CURRENT_TIMESTAMP WHERE player_id = ?`;
    await this.execute(sql, params);

    return this.findById(playerId);
  }

  async deletePlayer(playerId) {
    await this.execute('DELETE FROM players WHERE player_id = ?', [playerId]);
    return true;
  }

  _mapRowToEntity(p) {
    return {
      playerId: p.player_id,
      name: p.name,
      coins: p.coins,
      matchesPlayed: p.matches_played,
      gamesWon: p.games_won,
      roleStats: typeof p.role_stats === 'string' ? JSON.parse(p.role_stats) : p.role_stats,
      customization: typeof p.customization === 'string' ? JSON.parse(p.customization) : p.customization,
      deviceInfo: p.device_info,
      createdAt: p.created_at,
      updatedAt: p.updated_at,
    };
  }
}

module.exports = PlayerRepository;
