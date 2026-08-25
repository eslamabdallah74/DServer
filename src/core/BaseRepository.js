const Database = require('./Database');

class BaseRepository {
  constructor(tableName) {
    this.tableName = tableName;
  }

  get db() {
    return Database.getInstance().getPool();
  }

  async query(sql, params = []) {
    const [rows] = await this.db.query(sql, params);
    return rows;
  }

  async execute(sql, params = []) {
    const [result] = await this.db.execute(sql, params);
    return result;
  }

  async count(whereClause = '', params = []) {
    const sql = `SELECT COUNT(*) as total FROM ${this.tableName} ${whereClause}`;
    const rows = await this.query(sql, params);
    return rows[0]?.total || 0;
  }
}

module.exports = BaseRepository;
