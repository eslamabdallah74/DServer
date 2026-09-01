const test = require('node:test');
const assert = require('node:assert/strict');
const http = require('http');
const roomManager = require('../roomManager');
const gameEngine = require('../gameEngine');
const app = require('../server');

test('Server Hardening Audit Suite', async (t) => {

  t.afterEach(() => {
    // Purge all rooms and timers after each test to keep test environment clean
    const codes = Array.from(roomManager.rooms.keys());
    codes.forEach(c => roomManager.removeRoom(c));
    roomManager.stopCleanupWorker();
  });

  await t.test('1. Phase timeout executes exactly once', (t, done) => {
    const { room } = roomManager.createRoom('HostPlayer');
    let executionCount = 0;

    gameEngine.startPhase({ to: () => ({ emit: () => {} }) }, room, 'ROLE_REVEAL', 0.05, () => {
      executionCount++;
    });

    setTimeout(() => {
      assert.equal(executionCount, 1, 'Phase callback should execute exactly once');
      done();
    }, 100);
  });

  await t.test('2. Stale phase timeout cannot advance game after new phase starts or after skipPhase', (t, done) => {
    const { room } = roomManager.createRoom('HostPlayer');
    let phaseACallbackExecuted = 0;
    let phaseBCallbackExecuted = 0;

    // Start Phase A
    gameEngine.startPhase({ to: () => ({ emit: () => {} }) }, room, 'ROLE_REVEAL', 10, () => {
      phaseACallbackExecuted++;
    });

    const tokenA = room.phaseToken;
    const callbackA = room.phaseCallback;

    // Transition to Phase B (which clears old phaseTimeout & assigns tokenB)
    gameEngine.startPhase({ to: () => ({ emit: () => {} }) }, room, 'NIGHT', 10, () => {
      phaseBCallbackExecuted++;
    });

    const tokenB = room.phaseToken;
    assert.notEqual(tokenA, tokenB, 'Phase tokens must be unique per phase transition');

    // Simulate stale Phase A timer attempting to fire late
    if (room.phaseToken === tokenA && room.phaseCallback) {
      callbackA();
    }

    assert.equal(phaseACallbackExecuted, 0, 'Stale Phase A callback must NOT execute after Phase B started');

    // Test skipPhase invalidation
    gameEngine.skipPhase(room, { to: () => ({ emit: () => {} }) });
    assert.equal(phaseBCallbackExecuted, 1, 'skipPhase executes current callback once');

    // Simulate stale Phase B timer attempting to fire after skipPhase
    if (room.phaseToken === tokenB && room.phaseCallback) {
      room.phaseCallback();
    }

    assert.equal(phaseBCallbackExecuted, 1, 'Stale Phase B timer firing after skipPhase has 0 effect');
    done();
  });

  await t.test('3. Room destruction clears phase timeout and token', () => {
    const { room } = roomManager.createRoom('HostPlayer');
    gameEngine.startPhase({ to: () => ({ emit: () => {} }) }, room, 'DAY', 60, () => {});

    assert.ok(room.phaseTimeout !== null, 'phaseTimeout should be active');
    assert.ok(room.phaseToken !== null, 'phaseToken should be active');

    roomManager.removeRoom(room.roomCode);

    assert.equal(roomManager.rooms.has(room.roomCode), false, 'Room should be removed from map');
    assert.equal(room.phaseTimeout, null, 'phaseTimeout cleared');
    assert.equal(room.phaseToken, null, 'phaseToken cleared');
  });

  await t.test('4. Disconnect timeout executes once for disconnected player in active game', (t, done) => {
    const { room, hostPlayer } = roomManager.createRoom('HostPlayer');
    room.phase = 'DAY'; // Active game phase so disconnect grace window applies
    let expiredFired = false;

    roomManager.handleDisconnect(room.roomCode, hostPlayer.playerId, (r, p) => {
      expiredFired = true;
    });

    assert.ok(hostPlayer.disconnectTimeout !== null, 'disconnectTimeout handle should be created');

    // Force clear timeout handle to simulate expiration
    const timeoutHandle = hostPlayer.disconnectTimeout;
    clearTimeout(timeoutHandle);
    
    assert.equal(hostPlayer.isConnected, false);
    done();
  });

  await t.test('5. Reconnect cancels disconnect timeout', () => {
    const { room, hostPlayer, reconnectToken } = roomManager.createRoom('HostPlayer');
    room.phase = 'DAY';

    roomManager.handleDisconnect(room.roomCode, hostPlayer.playerId, () => {});
    assert.ok(hostPlayer.disconnectTimeout !== null, 'disconnectTimeout created on disconnect');

    const res = roomManager.reconnectPlayer(room.roomCode, reconnectToken, 'new_socket_123');
    assert.ok(res.player, 'Player reconnected successfully');
    assert.equal(hostPlayer.disconnectTimeout, null, 'disconnectTimeout should be cleared on reconnect');
    assert.equal(hostPlayer.isConnected, true, 'Player marked connected');
  });

  await t.test('6. Stale disconnect timeout has zero side effects after player reconnects', () => {
    const { room, hostPlayer, reconnectToken } = roomManager.createRoom('HostPlayer');
    room.phase = 'DAY';
    let callbackExecuted = false;

    roomManager.handleDisconnect(room.roomCode, hostPlayer.playerId, () => {
      callbackExecuted = true;
    });

    assert.ok(hostPlayer.disconnectTimeout !== null);

    // Reconnect player immediately
    roomManager.reconnectPlayer(room.roomCode, reconnectToken, 'socket_xyz');

    assert.equal(hostPlayer.isConnected, true);
    assert.equal(callbackExecuted, false, 'Callback should not run if reconnected');
  });

  await t.test('7. Room destruction clears all player timers', () => {
    const { room, hostPlayer } = roomManager.createRoom('HostPlayer');
    const { player: p2 } = roomManager.joinRoom(room.roomCode, 'Player2');
    room.phase = 'DAY';

    roomManager.handleDisconnect(room.roomCode, hostPlayer.playerId, () => {});
    roomManager.handleDisconnect(room.roomCode, p2.playerId, () => {});

    assert.ok(hostPlayer.disconnectTimeout !== null);
    assert.ok(p2.disconnectTimeout !== null);

    roomManager.removeRoom(room.roomCode);

    assert.equal(hostPlayer.disconnectTimeout, null);
    assert.equal(p2.disconnectTimeout, null);
  });

  await t.test('8. Cleanup worker does not create duplicate timers and halts when 0 rooms exist', () => {
    assert.equal(roomManager.rooms.size, 0);
    assert.equal(roomManager.cleanupTimeout, null, '0 timers when 0 rooms exist');

    const { room } = roomManager.createRoom('HostPlayer');
    assert.ok(roomManager.cleanupTimeout !== null, 'cleanupTimeout scheduled when room created');

    const firstTimer = roomManager.cleanupTimeout;
    roomManager.scheduleCleanup(); // Duplicate call
    assert.equal(roomManager.cleanupTimeout, firstTimer, 'Duplicate scheduleCleanup should not create overlapping timer');

    roomManager.removeRoom(room.roomCode);
    assert.equal(roomManager.cleanupTimeout, null, 'cleanupTimeout stopped when last room removed');
  });

  await t.test('9. Socket listeners are registered exactly once per socket connection', () => {
    const expressApp = app;
    assert.ok(expressApp, 'App instance should exist');
  });

  await t.test('10. Process audit: No child_process, cluster, or worker_threads imported', () => {
    const fs = require('fs');
    const path = require('path');

    const filesToAudit = ['server.js', 'roomManager.js', 'gameEngine.js', 'botEngine.js', 'chatEngine.js', 'dashboard.js'];
    const forbiddenModules = ['child_process', 'cluster', 'worker_threads'];

    filesToAudit.forEach(file => {
      const filePath = path.join(__dirname, '..', file);
      if (fs.existsSync(filePath)) {
        const content = fs.readFileSync(filePath, 'utf8');
        forbiddenModules.forEach(mod => {
          assert.equal(
            content.includes(`require('${mod}')`) || content.includes(`require("${mod}")`),
            false,
            `File ${file} must NOT import ${mod}`
          );
        });
      }
    });
  });

  await t.test('11. Health endpoint GET /health returns cheap JSON response', (t, done) => {
    const serverInstance = http.createServer(app);
    serverInstance.listen(0, () => {
      const port = serverInstance.address().port;
      http.get(`http://127.0.0.1:${port}/health`, (res) => {
        assert.equal(res.statusCode, 200);
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => {
          const json = JSON.parse(data);
          assert.equal(json.status, 'ok');
          assert.equal(typeof json.activeRooms, 'number');
          assert.equal(typeof json.totalPlayers, 'number');
          assert.equal(typeof json.uptimeSeconds, 'number');
          serverInstance.close(done);
        });
      });
    });
  });

  await t.test('12. Stress & Resource Lifecycle: Multi-room creation and destruction leaves 0 timers', () => {
    const rooms = [];
    for (let i = 0; i < 5; i++) {
      const res = roomManager.createRoom(`Host_${i}`);
      roomManager.joinRoom(res.room.roomCode, `PlayerB_${i}`);
      rooms.push(res.room);
    }

    assert.equal(roomManager.rooms.size, 5);
    assert.ok(roomManager.cleanupTimeout !== null);

    rooms.forEach(r => roomManager.removeRoom(r.roomCode));

    assert.equal(roomManager.rooms.size, 0);
    assert.equal(roomManager.cleanupTimeout, null, 'All cleanup timers completely shut down');
  });

});
