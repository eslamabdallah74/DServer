/**
 * cPanel Phusion Passenger Startup File for Deceit Server
 * Ensures clean application bootstrapping without crash loops or process proliferation.
 */

// Global crash protection for cPanel Phusion Passenger
process.on('uncaughtException', (err) => {
  console.error('[cPanel Passenger] Uncaught Exception:', err);
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('[cPanel Passenger] Unhandled Rejection:', reason);
});

const server = require('./server');

module.exports = server;
