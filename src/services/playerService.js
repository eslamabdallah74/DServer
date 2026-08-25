const { getPool } = require('../db/connection');

async function syncPlayerProfile(data) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

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

  if (!playerId || !name) {
    throw new Error('playerId and name are required');
  }

  const query = `
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

  await pool.query(query, [
    playerId,
    name,
    coins,
    matchesPlayed,
    gamesWon,
    roleStats ? JSON.stringify(roleStats) : null,
    customization ? JSON.stringify(customization) : null,
    typeof deviceInfo === 'object' ? JSON.stringify(deviceInfo) : deviceInfo,
  ]);

  return getPlayerById(playerId);
}

async function getPlayerById(playerId) {
  const pool = getPool();
  if (!pool) return null;

  const [rows] = await pool.query('SELECT * FROM players WHERE player_id = ?', [playerId]);
  if (rows.length === 0) return null;

  const p = rows[0];
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

async function listPlayers({ search = '', limit = 50, offset = 0 } = {}) {
  const pool = getPool();
  if (!pool) return { players: [], total: 0 };

  const parsedLimit = Math.max(1, Math.min(200, parseInt(limit, 10) || 50));
  const parsedOffset = Math.max(0, parseInt(offset, 10) || 0);

  let whereClause = '';
  const params = [];

  if (search.trim()) {
    whereClause = 'WHERE name LIKE ? OR player_id LIKE ?';
    const s = `%${search.trim()}%`;
    params.push(s, s);
  }

  const countQuery = `SELECT COUNT(*) as total FROM players ${whereClause}`;
  const [countRows] = await pool.query(countQuery, params);
  const total = countRows[0].total;

  const selectQuery = `
    SELECT * FROM players 
    ${whereClause} 
    ORDER BY updated_at DESC 
    LIMIT ? OFFSET ?
  `;
  params.push(parsedLimit, parsedOffset);
  const [rows] = await pool.query(selectQuery, params);

  const players = rows.map(p => ({
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
  }));

  return { players, total };
}

async function updatePlayer(playerId, { coins, name, matchesPlayed, gamesWon }) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  const updates = [];
  const params = [];

  if (coins !== undefined) {
    updates.push('coins = ?');
    params.push(coins);
  }
  if (name !== undefined) {
    updates.push('name = ?');
    params.push(name);
  }
  if (matchesPlayed !== undefined) {
    updates.push('matches_played = ?');
    params.push(matchesPlayed);
  }
  if (gamesWon !== undefined) {
    updates.push('games_won = ?');
    params.push(gamesWon);
  }

  if (updates.length === 0) return getPlayerById(playerId);

  params.push(playerId);
  const query = `UPDATE players SET ${updates.join(', ')}, updated_at = CURRENT_TIMESTAMP WHERE player_id = ?`;
  await pool.query(query, params);

  return getPlayerById(playerId);
}

async function deletePlayer(playerId) {
  const pool = getPool();
  if (!pool) throw new Error('Database is offline');

  await pool.query('DELETE FROM players WHERE player_id = ?', [playerId]);
  return true;
}

module.exports = {
  syncPlayerProfile,
  getPlayerById,
  listPlayers,
  updatePlayer,
  deletePlayer,
};
