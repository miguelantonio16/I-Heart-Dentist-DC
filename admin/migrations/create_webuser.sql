CREATE TABLE IF NOT EXISTS webuser (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  usertype CHAR(1) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- No-op insert example: (not inserting any users here)