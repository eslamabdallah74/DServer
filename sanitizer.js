/**
 * sanitizer.js — String & Input Sanitization Utilities for Deceit Server
 */

function sanitizeInput(input, maxLength = 255) {
  if (input === null || input === undefined) return '';
  let str = String(input);

  // 1. Remove HTML tags
  str = str.replace(/<[^>]*>/g, '');

  // 2. Remove non-printable control characters
  str = str.replace(/[\x00-\x1F\x7F-\x9F]/g, '');

  // 3. Trim whitespace
  str = str.trim();

  // 4. Truncate to maximum allowed length
  if (str.length > maxLength) {
    str = str.substring(0, maxLength);
  }

  return str;
}

function isValidRoomCode(code) {
  if (typeof code !== 'string') return false;
  return /^[A-Z0-9]{6}$/.test(code.trim().toUpperCase());
}

function isValidPlayerId(id) {
  if (typeof id !== 'string') return false;
  return id.trim().length > 0 && id.trim().length <= 100;
}

module.exports = {
  sanitizeInput,
  isValidRoomCode,
  isValidPlayerId,
};
