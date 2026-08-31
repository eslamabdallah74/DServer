const GamePhase = require('../enums/GamePhase');

class Room {
  constructor(roomCode, hostPlayerId, customSettings = {}) {
    this.roomCode = roomCode;
    this.hostPlayerId = hostPlayerId;
    this.settings = {
      maxPlayers: 16,
      dayTimerSeconds: 120,
      nightTimerSeconds: 60,
      votingTimerSeconds: 60,
      tieRule: 'NO_ELIMINATION',
      kingMustSurvive: true,
      revealEliminatedRole: true,
      enabledRoleIds: [],
      ...customSettings,
    };

    this.phase = GamePhase.LOBBY;
    this.phaseStartedAt = Date.now();
    this.phaseEndsAt = 0;
    this.createdAt = Date.now();
    this.lastActivityAt = Date.now();
    this.eventSequence = 100;
    this.round = 0;
    this.phaseToken = 0;

    this.players = [];
    this.nightActions = new Map();
    this.votes = new Map();
    this.taskProgress = 0;
    this.darknessActive = false;
    this.chatHistory = [];

    this.timerInterval = null;
    this.botTimeouts = [];
    this.vacantThrone = false;
    this.lastEliminatedPlayer = null;
    this.lastEliminatedCause = null;
    this.lastNightOutcome = null;
    this.victoryOutcome = null;
  }

  touch() {
    this.lastActivityAt = Date.now();
  }

  clearPhaseTimers() {
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }
    this.botTimeouts.forEach(t => clearTimeout(t));
    this.botTimeouts = [];
  }

  addBotTimeout(handle) {
    this.botTimeouts.push(handle);
  }

  transitionTo(nextPhase, durationSeconds = 0) {
    this.clearPhaseTimers();
    this.phase = nextPhase;
    this.phaseToken++;
    this.phaseStartedAt = Date.now();
    this.phaseEndsAt = durationSeconds > 0 ? Date.now() + (durationSeconds * 1000) : 0;
    this.touch();

    // Reset player readiness
    this.players.forEach(p => { p.isReady = false; });
  }

  destroy() {
    this.clearPhaseTimers();
    this.players.forEach(p => p.cleanTimers());
    this.players = [];
    this.nightActions.clear();
    this.votes.clear();
  }
}

module.exports = Room;
