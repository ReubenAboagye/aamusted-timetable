-- Admin-only authentication schema (minimal)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: seed a default admin if none exists (username: admin, password to be set manually)
INSERT INTO users (username, password_hash, is_admin, is_active)
SELECT 'admin', '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- NOTE: Replace the placeholder hash with a real bcrypt hash after deployment.


