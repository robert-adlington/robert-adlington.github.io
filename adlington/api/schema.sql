-- ============================================
-- Adlington.fr Database Schema
-- Contract Whist Game Database
-- ============================================
--
-- This schema adds tables to your existing database.
-- Run this against your existing database:
--   mysql -u user -p your_database_name < schema.sql
--
-- The tables will be created with IF NOT EXISTS to avoid
-- overwriting any existing data.
-- ============================================

-- ============================================
-- Users Table
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Sessions Table
-- ============================================
CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(128) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Contract Whist Games Table
-- ============================================
CREATE TABLE IF NOT EXISTS whist_games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  game_name VARCHAR(255) NOT NULL,
  players JSON NOT NULL COMMENT 'Array of player names',
  scores JSON NOT NULL COMMENT 'Array of round scores with bids and actual scores',
  current_round INT UNSIGNED DEFAULT 1,
  is_complete BOOLEAN DEFAULT FALSE,
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_is_complete (is_complete),
  INDEX idx_played_at (played_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Sample Admin User
-- Default password: 'changeme123'
-- IMPORTANT: Change this password after first login!
-- ============================================
INSERT INTO users (username, email, password_hash, is_admin)
VALUES (
  'admin',
  'admin@adlington.fr',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  TRUE
) ON DUPLICATE KEY UPDATE username = username;

-- ============================================
-- Clean up expired sessions (maintenance query)
-- Run this periodically via cron job
-- ============================================
-- DELETE FROM sessions WHERE expires_at < NOW();
