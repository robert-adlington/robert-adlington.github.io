-- ============================================
-- Adlington.fr Database Schema
-- Contract Whist Game Database
-- ============================================
--
-- This schema supports both fresh installs and migrations.
-- Safe to run multiple times (idempotent).
--
-- Run against your database:
--   mysql -u user -p your_database_name < schema.sql
--
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
-- Password Reset Tokens Table
-- ============================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  token VARCHAR(64) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_expires_at (expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fix existing password_reset_tokens table if it has wrong column type
DROP PROCEDURE IF EXISTS fix_password_reset_tokens;
DELIMITER //
CREATE PROCEDURE fix_password_reset_tokens()
BEGIN
  DECLARE col_type VARCHAR(64);

  SELECT COLUMN_TYPE INTO col_type
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'password_reset_tokens'
  AND COLUMN_NAME = 'user_id';

  IF col_type = 'int' THEN
    ALTER TABLE password_reset_tokens MODIFY COLUMN user_id INT UNSIGNED NOT NULL;
  END IF;
END //
DELIMITER ;
CALL fix_password_reset_tokens();
DROP PROCEDURE IF EXISTS fix_password_reset_tokens;

-- ============================================
-- Players Table
-- ============================================
-- Players are unique by name and can be shared across user accounts.
-- A player has play statistics aggregated across all games they play,
-- regardless of which user account recorded those games.
-- ============================================
CREATE TABLE IF NOT EXISTS players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique player name identifier',
  created_by_user_id INT UNSIGNED NULL COMMENT 'User who first created this player',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- User Friends Table (Favorites)
-- ============================================
-- Associates users with their favorite players.
-- A user can have multiple friends, and a player can be
-- a friend of multiple users.
-- Removing a friend does not delete the player record.
-- ============================================
CREATE TABLE IF NOT EXISTS user_friends (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_player (user_id, player_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_player_id (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Contract Whist Games Table
-- ============================================
CREATE TABLE IF NOT EXISTS whist_games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  game_name VARCHAR(255) NOT NULL,
  players JSON NOT NULL COMMENT 'Array of player names (legacy support)',
  scores JSON NOT NULL COMMENT 'Array of round scores with bids and actual scores',
  current_round INT UNSIGNED DEFAULT 1,
  is_complete BOOLEAN DEFAULT FALSE,
  ip_address VARCHAR(45) NULL COMMENT 'IP address for location tracking',
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_is_complete (is_complete),
  INDEX idx_played_at (played_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MIGRATION: Add ip_address column to existing whist_games table
-- This procedure safely adds the column if it doesn't exist
-- ============================================
DROP PROCEDURE IF EXISTS add_ip_address_column;
DELIMITER //
CREATE PROCEDURE add_ip_address_column()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'whist_games'
    AND COLUMN_NAME = 'ip_address'
  ) THEN
    ALTER TABLE whist_games
    ADD COLUMN ip_address VARCHAR(45) NULL
    COMMENT 'IP address for location tracking'
    AFTER is_complete;
  END IF;
END //
DELIMITER ;
CALL add_ip_address_column();
DROP PROCEDURE IF EXISTS add_ip_address_column;

-- ============================================
-- Game Players Table
-- ============================================
-- Links games to player records for proper statistics tracking.
-- Each player in a game has a position (0-indexed order).
-- ============================================
CREATE TABLE IF NOT EXISTS game_players (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id INT UNSIGNED NOT NULL,
  player_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL COMMENT 'Player position in game (0-indexed)',
  final_score INT NULL COMMENT 'Final score when game is complete',
  won BOOLEAN DEFAULT FALSE COMMENT 'Whether this player won the game',
  UNIQUE KEY unique_game_position (game_id, position),
  UNIQUE KEY unique_game_player (game_id, player_id),
  FOREIGN KEY (game_id) REFERENCES whist_games(id) ON DELETE CASCADE,
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  INDEX idx_game_id (game_id),
  INDEX idx_player_id (player_id)
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
-- MIGRATION: Populate players from existing games
-- ============================================
-- This creates player records from existing game data.
-- Safe to run multiple times (uses INSERT IGNORE).
-- ============================================
DROP PROCEDURE IF EXISTS migrate_players_from_games;
DELIMITER //
CREATE PROCEDURE migrate_players_from_games()
BEGIN
  -- Check if there are any games without corresponding player records
  IF EXISTS (SELECT 1 FROM whist_games LIMIT 1) THEN
    INSERT IGNORE INTO players (name, created_by_user_id)
    SELECT DISTINCT j.player_name, g.user_id
    FROM whist_games g,
    JSON_TABLE(g.players, '$[*]' COLUMNS (player_name VARCHAR(100) PATH '$')) j
    WHERE j.player_name IS NOT NULL
    AND j.player_name != ''
    AND NOT EXISTS (SELECT 1 FROM players p WHERE p.name = j.player_name);
  END IF;
END //
DELIMITER ;
CALL migrate_players_from_games();
DROP PROCEDURE IF EXISTS migrate_players_from_games;

-- ============================================
-- MIGRATION: Link existing game players to player records
-- ============================================
-- Creates game_players records for existing games.
-- Safe to run multiple times (uses INSERT IGNORE).
-- ============================================
DROP PROCEDURE IF EXISTS migrate_game_players;
DELIMITER //
CREATE PROCEDURE migrate_game_players()
BEGIN
  -- Link games to players where not already linked
  INSERT IGNORE INTO game_players (game_id, player_id, position, final_score, won)
  SELECT
      g.id as game_id,
      p.id as player_id,
      j.pos - 1 as position,  -- JSON_TABLE ORDINALITY is 1-based, we want 0-based
      NULL as final_score,
      FALSE as won
  FROM whist_games g,
  JSON_TABLE(g.players, '$[*]' COLUMNS (
      pos FOR ORDINALITY,
      player_name VARCHAR(100) PATH '$'
  )) j
  JOIN players p ON p.name = j.player_name
  WHERE NOT EXISTS (
    SELECT 1 FROM game_players gp
    WHERE gp.game_id = g.id AND gp.player_id = p.id
  );
END //
DELIMITER ;
CALL migrate_game_players();
DROP PROCEDURE IF EXISTS migrate_game_players;

-- ============================================
-- Clean up expired sessions (maintenance query)
-- Run this periodically via cron job
-- ============================================
-- DELETE FROM sessions WHERE expires_at < NOW();

-- ============================================
-- Clean up expired password reset tokens (maintenance query)
-- Run this periodically via cron job
-- ============================================
-- DELETE FROM password_reset_tokens WHERE expires_at < NOW();
