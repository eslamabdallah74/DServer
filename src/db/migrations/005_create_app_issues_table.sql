CREATE TABLE IF NOT EXISTS app_issues (
  issue_id VARCHAR(64) PRIMARY KEY,
  severity ENUM('warning', 'error', 'critical') NOT NULL DEFAULT 'error',
  page VARCHAR(100) DEFAULT NULL,
  method VARCHAR(100) DEFAULT NULL,
  location VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  error_details TEXT DEFAULT NULL,
  stack_trace TEXT DEFAULT NULL,
  player_id VARCHAR(64) DEFAULT NULL,
  player_name VARCHAR(100) DEFAULT NULL,
  app_version VARCHAR(50) DEFAULT NULL,
  device_info TEXT DEFAULT NULL,
  context JSON DEFAULT NULL,
  formatted_date VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
