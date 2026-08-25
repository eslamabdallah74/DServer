CREATE TABLE IF NOT EXISTS match_ratings (
  rating_id VARCHAR(64) PRIMARY KEY,
  match_id VARCHAR(64) NOT NULL,
  device_id VARCHAR(64) DEFAULT NULL,
  rating INT NOT NULL,
  feedback_category VARCHAR(50) DEFAULT 'gameplay',
  comment TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
