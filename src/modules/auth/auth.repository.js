const BaseRepository = require('../../core/BaseRepository');

class AuthRepository extends BaseRepository {
  constructor() {
    super('admins');
  }

  async findByUsernameOrEmail(identifier) {
    const query = `
      SELECT * FROM admins 
      WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
      LIMIT 1
    `;
    const rows = await this.query(query, [identifier.trim(), identifier.trim()]);
    return rows[0] || null;
  }

  async findById(adminId) {
    const query = 'SELECT id, username, email, created_at FROM admins WHERE id = ?';
    const rows = await this.query(query, [adminId]);
    return rows[0] || null;
  }
}

module.exports = AuthRepository;
