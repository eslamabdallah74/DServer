const { query, isDbConnected } = require('../core/Database');

const inMemoryProfiles = new Map();

class PlayerProfileRepository {
  static async findById(playerId) {
    if (!isDbConnected()) {
      return inMemoryProfiles.get(playerId) || null;
    }

    try {
      const [rows] = await query('SELECT * FROM player_profiles WHERE player_id = ?', [playerId]);
      if (rows.length === 0) return null;

      const r = rows[0];
      return {
        playerId: r.player_id,
        nickname: r.nickname,
        coins: r.coins,
        ownedRoles: JSON.parse(r.owned_roles_json || '[]'),
        stats: {
          wins: r.stats_wins || 0,
          losses: r.stats_losses || 0,
          matches: r.stats_matches || 0,
        },
        createdAt: r.created_at,
        updatedAt: r.updated_at,
      };
    } catch (err) {
      console.error('[PlayerProfileRepository] findById error:', err.message);
      return inMemoryProfiles.get(playerId) || null;
    }
  }

  static async syncProfile(payload) {
    const { playerId, nickname, coins = 0, ownedRoles = [], stats = {} } = payload;
    const incomingWins = stats.wins || 0;
    const incomingLosses = stats.losses || 0;
    const incomingMatches = stats.matches || 0;

    const inMemoryItem = {
      playerId,
      nickname: nickname || 'Player',
      coins,
      ownedRoles,
      stats: { wins: incomingWins, losses: incomingLosses, matches: incomingMatches },
      updatedAt: new Date().toISOString(),
    };
    inMemoryProfiles.set(playerId, inMemoryItem);

    if (!isDbConnected()) return inMemoryItem;

    try {
      const existing = await this.findById(playerId);

      if (!existing) {
        const sql = `
          INSERT INTO player_profiles (player_id, nickname, coins, owned_roles_json, stats_wins, stats_losses, stats_matches, last_seen_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, NOW());
        `;
        await query(sql, [
          playerId,
          nickname || 'Player',
          coins,
          JSON.stringify(ownedRoles),
          incomingWins,
          incomingLosses,
          incomingMatches,
        ]);
        return this.findById(playerId);
      }

      // Reconciliation logic: Math.max for coins/stats & Set Union for owned roles
      const reconciledCoins = Math.max(existing.coins, coins);
      const reconciledWins = Math.max(existing.stats.wins, incomingWins);
      const reconciledLosses = Math.max(existing.stats.losses, incomingLosses);
      const reconciledMatches = Math.max(existing.stats.matches, incomingMatches);

      const mergedRoles = Array.from(new Set([...existing.ownedRoles, ...ownedRoles]));

      const updateSql = `
        UPDATE player_profiles
        SET nickname = ?, coins = ?, owned_roles_json = ?, stats_wins = ?, stats_losses = ?, stats_matches = ?, last_seen_at = NOW()
        WHERE player_id = ?;
      `;
      await query(updateSql, [
        nickname || existing.nickname,
        reconciledCoins,
        JSON.stringify(mergedRoles),
        reconciledWins,
        reconciledLosses,
        reconciledMatches,
        playerId,
      ]);

      return this.findById(playerId);
    } catch (err) {
      console.error('[PlayerProfileRepository] syncProfile error:', err.message);
      return inMemoryItem;
    }
  }
}

module.exports = PlayerProfileRepository;
