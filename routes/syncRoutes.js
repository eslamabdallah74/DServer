const express = require('express');
const jwt = require('jsonwebtoken');
const { getPool, isDbConnected } = require('../db');

const router = express.Router();

const JWT_SECRET = process.env.JWT_SECRET || 'deceit_offline_super_secret_jwt_key_2026_998877665544332211';

// Request Logger Middleware
router.use((req, res, next) => {
  console.log(`[Sync API] 📡 Incoming ${req.method} ${req.originalUrl || req.url}`);
  if (req.body && Object.keys(req.body).length > 0) {
    console.log(`[Sync API Payload]:`, JSON.stringify(req.body));
  }
  next();
});

// Authentication Middleware supporting direct JWT_SECRET header or Bearer Token
function authenticateToken(req, res, next) {
  const secretHeader = req.headers['x-jwt-secret'] || req.headers['x-secret'] || req.headers['x-api-key'];
  const authHeader = req.headers['authorization'];
  const rawToken = authHeader && authHeader.startsWith('Bearer ') ? authHeader.split(' ')[1] : authHeader;

  // Direct JWT_SECRET verification (No Bearer token exchange needed!)
  if (secretHeader === JWT_SECRET || rawToken === JWT_SECRET) {
    req.user = { deviceId: 'secret_client', verifiedBySecret: true };
    return next();
  }

  if (rawToken) {
    return jwt.verify(rawToken, JWT_SECRET, (err, decoded) => {
      if (!err) {
        req.user = decoded;
        return next();
      }
      if (rawToken === JWT_SECRET) {
        req.user = { deviceId: 'secret_client' };
        return next();
      }
      req.user = { deviceId: 'fallback_client' };
      return next();
    });
  }

  req.user = { deviceId: 'default_client' };
  next();
}

// Token generation endpoint for app client initialization
router.post('/token', (req, res) => {
  const { deviceId, clientSecret } = req.body || {};
  console.log(`[Sync API] 🔑 Token requested for deviceId: ${deviceId}`);
  const payload = {
    deviceId: deviceId || 'anonymous_device',
    iat: Math.floor(Date.now() / 1000),
  };
  const token = jwt.sign(payload, JWT_SECRET, { expiresIn: '365d' });
  return res.json({ success: true, token });
});

const inMemoryStore = {
  players: new Map(),
  issues: [],
  feedback: [],
  matches: [],
};

// Helper: Reconcile existing DB player record with incoming offline/client payload
async function reconcileAndUpsertPlayer(pool, p) {
  const playerId = p.playerId;
  const incomingCoins = p.coins !== undefined ? Number(p.coins) : 0;
  const incomingPoints = p.points !== undefined ? Number(p.points) : 1;
  const incomingGames = Number(p.matchesPlayed || p.totalGames || 0);
  const incomingWins = Number(p.wins || 0);
  const incomingRoles = Array.isArray(p.ownedRoles) ? p.ownedRoles : [];
  const incomingTime = Number(p.lastSyncTimestamp || Date.now());

  let finalName = p.name || 'Guest';
  let finalCoins = incomingCoins;
  let finalPoints = incomingPoints;
  let finalGames = incomingGames;
  let finalWins = incomingWins;
  let finalRoles = incomingRoles;
  let finalTime = incomingTime;

  try {
    const [rows] = await pool.execute('SELECT * FROM player_profiles WHERE player_id = ? LIMIT 1', [playerId]);
    if (rows && rows.length > 0) {
      const dbRow = rows[0];
      let dbRoles = [];
      try {
        dbRoles = typeof dbRow.owned_roles === 'string' ? JSON.parse(dbRow.owned_roles) : (dbRow.owned_roles || []);
      } catch (_) {}

      finalName = p.name || dbRow.name || 'Guest';
      finalCoins = Math.max(Number(dbRow.coins || 0), incomingCoins);
      finalPoints = Math.max(Number(dbRow.points || 1), incomingPoints);
      finalGames = Math.max(Number(dbRow.matches_played || 0), incomingGames);
      finalWins = Math.max(Number(dbRow.wins || 0), incomingWins);
      finalRoles = Array.from(new Set([...dbRoles, ...incomingRoles]));
      finalTime = Math.max(Number(dbRow.last_sync_timestamp || 0), incomingTime, Date.now());
    }
  } catch (err) {
    console.warn(`[Sync API] Could not read existing record for ${playerId} before merge:`, err.message);
  }

  const safeBrand = p.deviceBrand ? String(p.deviceBrand).substring(0, 250) : null;
  const safeModel = p.deviceModel ? String(p.deviceModel).substring(0, 250) : null;
  const safeOs = p.deviceOs ? String(p.deviceOs).substring(0, 250) : null;
  const safeOsVersion = p.deviceOsVersion ? String(p.deviceOsVersion).substring(0, 1000) : null;
  const safeAppVersion = p.appVersion ? String(p.appVersion).substring(0, 250) : null;

  const sql = `
    INSERT INTO player_profiles (
      player_id, name, age, gender, phone_number, coins, points,
      matches_played, wins, owned_roles, device_brand, device_model,
      device_os, device_os_version, app_version, last_sync_timestamp
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      age = COALESCE(VALUES(age), age),
      gender = COALESCE(VALUES(gender), gender),
      phone_number = COALESCE(VALUES(phone_number), phone_number),
      coins = VALUES(coins),
      points = VALUES(points),
      matches_played = VALUES(matches_played),
      wins = VALUES(wins),
      owned_roles = VALUES(owned_roles),
      device_brand = COALESCE(VALUES(device_brand), device_brand),
      device_model = COALESCE(VALUES(device_model), device_model),
      device_os = COALESCE(VALUES(device_os), device_os),
      device_os_version = COALESCE(VALUES(device_os_version), device_os_version),
      app_version = COALESCE(VALUES(app_version), app_version),
      last_sync_timestamp = VALUES(last_sync_timestamp)
  `;

  await pool.execute(sql, [
    playerId,
    finalName,
    p.age || null,
    p.gender || null,
    p.phoneNumber || null,
    finalCoins,
    finalPoints,
    finalGames,
    finalWins,
    JSON.stringify(finalRoles),
    safeBrand,
    safeModel,
    safeOs,
    safeOsVersion,
    safeAppVersion,
    finalTime,
  ]);
}

// Background auto-flush function when DB restores connection
async function flushInMemoryStoreToDb() {
  if (!isDbConnected()) return;
  if (inMemoryStore.players.size === 0 &&
      inMemoryStore.issues.length === 0 &&
      inMemoryStore.feedback.length === 0 &&
      inMemoryStore.matches.length === 0) {
    return;
  }

  console.log(`[Sync API Flush] 🔄 Flushing in-memory queue to MySQL (Players: ${inMemoryStore.players.size}, Issues: ${inMemoryStore.issues.length}, Feedback: ${inMemoryStore.feedback.length})...`);
  const pool = getPool();

  // 1. Flush Players
  for (const [playerId, playerPayload] of inMemoryStore.players.entries()) {
    try {
      await reconcileAndUpsertPlayer(pool, playerPayload);
      inMemoryStore.players.delete(playerId);
    } catch (err) {
      console.error(`[Sync API Flush Error] Failed to flush player ${playerId}:`, err.message);
    }
  }

  // 2. Flush Issues
  while (inMemoryStore.issues.length > 0) {
    const issue = inMemoryStore.issues.shift();
    try {
      await pool.execute(`
        INSERT INTO app_issues (issue_id, title, description, stack_trace, app_version, device_info, status)
        VALUES (?, ?, ?, ?, ?, ?, 'open')
        ON DUPLICATE KEY UPDATE description = VALUES(description)
      `, [
        issue.id,
        issue.title,
        issue.description || '',
        issue.stackTrace || '',
        issue.appVersion || '',
        typeof issue.deviceInfo === 'object' ? JSON.stringify(issue.deviceInfo) : (issue.deviceInfo || ''),
      ]);
    } catch (err) {
      console.error(`[Sync API Flush Error] Failed to flush issue ${issue.id}:`, err.message);
    }
  }

  // 3. Flush Feedback
  while (inMemoryStore.feedback.length > 0) {
    const fb = inMemoryStore.feedback.shift();
    try {
      await pool.execute(`
        INSERT INTO user_feedback (feedback_id, rating, comment, category, contact_email)
        VALUES (?, ?, ?, ?, ?)
      `, [fb.id, fb.rating || 5, fb.comment || '', fb.category || 'general', fb.contactEmail || '']);
    } catch (err) {
      console.error(`[Sync API Flush Error] Failed to flush feedback ${fb.id}:`, err.message);
    }
  }

  // 4. Flush Matches
  while (inMemoryStore.matches.length > 0) {
    const m = inMemoryStore.matches.shift();
    try {
      await pool.execute(`
        INSERT INTO match_logs (match_id, player_count, winner_team, roles_used, duration_seconds)
        VALUES (?, ?, ?, ?, ?)
      `, [m.id, m.playerCount || 0, m.winnerTeam || 'unknown', JSON.stringify(m.rolesUsed || []), m.durationSeconds || 0]);
    } catch (err) {
      console.error(`[Sync API Flush Error] Failed to flush match log ${m.id}:`, err.message);
    }
  }

  console.log('[Sync API Flush] ✅ Successfully flushed in-memory store to MySQL.');
}

// Set periodic flush timer (every 15 seconds)
setInterval(() => {
  flushInMemoryStoreToDb().catch(err => {
    console.error('[Sync API Flush Interval Error]:', err.message);
  });
}, 15000);

// ─── 1. Player Profile Endpoints ──────────────────────────────────────────────

// Sync / Upsert Player Profile (Creates a new record if missing, updates if present)
router.post('/player', async (req, res) => {
  try {
    const payload = req.body || {};
    const { playerId, name, coins, points } = payload;

    if (!playerId) {
      console.warn('[Sync API] ⚠️ Rejected /player request: playerId is missing');
      return res.status(400).json({ error: 'INVALID_PLAYER_ID', message: 'playerId is required' });
    }

    console.log(`[Sync API] 👤 Upserting Player Profile -> ID: ${playerId}, Name: "${name}", Coins: ${coins}, Points: ${points}`);

    if (!isDbConnected()) {
      console.log(`[Sync API] ℹ️ MySQL DB is offline. Saving profile for ${playerId} in memory.`);
      const existing = inMemoryStore.players.get(playerId) || {};
      const existingRoles = Array.isArray(existing.ownedRoles) ? existing.ownedRoles : [];
      const incomingRoles = Array.isArray(payload.ownedRoles) ? payload.ownedRoles : [];
      const updated = {
        ...existing,
        ...payload,
        playerId,
        name: name || existing.name || 'Guest',
        coins: coins !== undefined ? Math.max(Number(existing.coins || 0), Number(coins)) : (existing.coins || 0),
        points: points !== undefined ? Math.max(Number(existing.points || 1), Number(points)) : (existing.points || 1),
        matchesPlayed: Math.max(Number(existing.matchesPlayed || 0), Number(payload.matchesPlayed || payload.totalGames || 0)),
        wins: Math.max(Number(existing.wins || 0), Number(payload.wins || 0)),
        ownedRoles: Array.from(new Set([...existingRoles, ...incomingRoles])),
        lastSyncTimestamp: Math.max(Number(existing.lastSyncTimestamp || 0), Number(payload.lastSyncTimestamp || Date.now())),
      };
      inMemoryStore.players.set(playerId, updated);
      return res.json({ success: true, offline: true, message: 'Saved to in-memory store' });
    }

    const pool = getPool();
    await reconcileAndUpsertPlayer(pool, payload);

    console.log(`[Sync API] ✅ Player profile ${playerId} ("${name}") successfully reconciled and saved to MySQL.`);
    return res.json({ success: true, message: 'Player profile saved successfully' });
  } catch (err) {
    console.error('[Sync API Error] Save Player Profile failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});



// Get Player Profile
router.get('/player/:playerId', async (req, res) => {
  try {
    const { playerId } = req.params;
    console.log(`[Sync API] 🔍 Fetching profile for playerId: ${playerId}`);

    if (!isDbConnected()) {
      const cached = inMemoryStore.players.get(playerId);
      if (cached) {
        return res.json({ success: true, offline: true, data: cached });
      }
      return res.json({
        success: true,
        offline: true,
        data: {
          playerId,
          name: 'Offline Player',
          coins: 100,
          points: 1,
          matchesPlayed: 0,
          wins: 0,
          ownedRoles: [],
        },
      });
    }

    const pool = getPool();
    const [rows] = await pool.execute('SELECT * FROM player_profiles WHERE player_id = ? LIMIT 1', [playerId]);

    if (!rows || rows.length === 0) {
      console.warn(`[Sync API] ⚠️ Player profile not found for ID: ${playerId}`);
      return res.status(404).json({ error: 'NOT_FOUND', message: 'Player profile not found' });
    }

    const row = rows[0];
    let ownedRoles = [];
    try {
      ownedRoles = typeof row.owned_roles === 'string' ? JSON.parse(row.owned_roles) : (row.owned_roles || []);
    } catch (_) {}

    console.log(`[Sync API] ✅ Fetched profile for ${playerId} ("${row.name}")`);
    return res.json({
      success: true,
      data: {
        playerId: row.player_id,
        name: row.name,
        age: row.age,
        gender: row.gender,
        phoneNumber: row.phone_number,
        coins: row.coins,
        points: row.points,
        matchesPlayed: row.matches_played,
        totalGames: row.matches_played,
        wins: row.wins,
        ownedRoles: ownedRoles,
        deviceBrand: row.device_brand,
        deviceModel: row.device_model,
        deviceOs: row.device_os,
        deviceOsVersion: row.device_os_version,
        appVersion: row.app_version,
        lastSyncTimestamp: row.last_sync_timestamp,
      },
    });
  } catch (err) {
    console.error('[Sync API Error] Get Player Profile failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Increment Player Coins
router.post('/coins/increment', async (req, res) => {
  try {
    const { playerId, amount } = req.body || {};
    if (!playerId || typeof amount !== 'number') {
      return res.status(400).json({ error: 'INVALID_PARAMS', message: 'playerId and numeric amount required' });
    }

    console.log(`[Sync API] 🪙 Incrementing coins for ${playerId} by +${amount}`);

    if (!isDbConnected()) {
      const existing = inMemoryStore.players.get(playerId) || { playerId, coins: 0 };
      existing.coins = (existing.coins || 0) + amount;
      inMemoryStore.players.set(playerId, existing);
      return res.json({ success: true, offline: true, message: `Coins incremented by ${amount}` });
    }

    const pool = getPool();
    const sql = `
      INSERT INTO player_profiles (player_id, coins, last_sync_timestamp)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE
        coins = coins + VALUES(coins),
        last_sync_timestamp = VALUES(last_sync_timestamp)
    `;

    await pool.execute(sql, [playerId, amount, Date.now()]);
    console.log(`[Sync API] ✅ Incremented coins for ${playerId}`);
    return res.json({ success: true, message: `Coins incremented by ${amount}` });
  } catch (err) {
    console.error('[Sync API Error] Increment Coins failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// ─── 2. App Issues Endpoints ──────────────────────────────────────────────────

// Log App Issue
router.post('/issues', async (req, res) => {
  try {
    const { issueId, title, description, stackTrace, appVersion, deviceInfo } = req.body || {};
    if (!title) {
      return res.status(400).json({ error: 'INVALID_TITLE', message: 'Issue title is required' });
    }

    console.log(`[Sync API] 🐛 Logging app issue: "${title}"`);
    const id = issueId || `issue_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;

    if (!isDbConnected()) {
      inMemoryStore.issues.unshift({ id, title, description, stackTrace, appVersion, deviceInfo, createdAt: new Date() });
      return res.json({ success: true, offline: true, issueId: id });
    }

    const pool = getPool();
    const sql = `
      INSERT INTO app_issues (issue_id, title, description, stack_trace, app_version, device_info, status)
      VALUES (?, ?, ?, ?, ?, ?, 'open')
      ON DUPLICATE KEY UPDATE
        description = VALUES(description),
        stack_trace = VALUES(stack_trace)
    `;

    await pool.execute(sql, [
      id,
      title,
      description || '',
      stackTrace || '',
      appVersion || '',
      typeof deviceInfo === 'object' ? JSON.stringify(deviceInfo) : (deviceInfo || ''),
    ]);

    return res.json({ success: true, issueId: id });
  } catch (err) {
    console.error('[Sync API Error] Log Issue failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// Get App Issues
router.get('/issues', async (req, res) => {
  try {
    if (!isDbConnected()) {
      return res.json({ success: true, offline: true, data: inMemoryStore.issues });
    }
    const pool = getPool();
    const [rows] = await pool.execute('SELECT * FROM app_issues ORDER BY created_at DESC LIMIT 100');
    return res.json({ success: true, data: rows });
  } catch (err) {
    console.error('[Sync API Error] Get Issues failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// ─── 3. User Feedback Endpoint ────────────────────────────────────────────────

router.post('/feedback', async (req, res) => {
  try {
    const { feedbackId, rating, comment, category, contactEmail } = req.body || {};
    console.log(`[Sync API] 💬 Received user feedback (Rating: ${rating})`);
    const id = feedbackId || `fb_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;

    if (!isDbConnected()) {
      inMemoryStore.feedback.unshift({ id, rating, comment, category, contactEmail, createdAt: new Date() });
      return res.json({ success: true, offline: true, feedbackId: id });
    }

    const pool = getPool();
    const sql = `
      INSERT INTO user_feedback (feedback_id, rating, comment, category, contact_email)
      VALUES (?, ?, ?, ?, ?)
    `;

    await pool.execute(sql, [
      id,
      rating || 5,
      comment || '',
      category || 'general',
      contactEmail || '',
    ]);

    return res.json({ success: true, feedbackId: id });
  } catch (err) {
    console.error('[Sync API Error] Feedback failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});

// ─── 4. Match Analytics Endpoint ──────────────────────────────────────────────

router.post('/matches/log', async (req, res) => {
  try {
    const { matchId, playerCount, winnerTeam, rolesUsed, durationSeconds } = req.body || {};
    console.log(`[Sync API] 📊 Logging match analytics: ${matchId} (${playerCount} players, Winner: ${winnerTeam})`);
    const id = matchId || `match_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;

    if (!isDbConnected()) {
      inMemoryStore.matches.unshift({ id, playerCount, winnerTeam, rolesUsed, durationSeconds, createdAt: new Date() });
      return res.json({ success: true, offline: true, matchId: id });
    }

    const pool = getPool();
    const sql = `
      INSERT INTO match_logs (match_id, player_count, winner_team, roles_used, duration_seconds)
      VALUES (?, ?, ?, ?, ?)
    `;

    await pool.execute(sql, [
      id,
      playerCount || 0,
      winnerTeam || 'unknown',
      JSON.stringify(rolesUsed || []),
      durationSeconds || 0,
    ]);

    return res.json({ success: true, matchId: id });
  } catch (err) {
    console.error('[Sync API Error] Match Log failed:', err);
    return res.status(500).json({ error: 'SERVER_ERROR', message: err.message });
  }
});


module.exports = router;
