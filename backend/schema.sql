-- TartarusLog schema


CREATE DATABASE IF NOT EXISTS tartaruslog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE interface;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  title VARCHAR(255) NULL,
  date DATE NOT NULL,
  block VARCHAR(64) NOT NULL,
  floor INT NOT NULL,
  start_floor INT NULL,
  end_floor INT NULL,
  blocks_covered JSON NULL,
  co_authors JSON NULL,
  party_members JSON NULL,
  difficulty VARCHAR(32) NULL,
  exploration_goal TEXT NULL,
  outcome TEXT NULL,
  strategy_notes TEXT NOT NULL,
  overall_notes TEXT NULL,
  shadows JSON NULL,
  gatekeeper JSON NULL,
  treasure JSON NULL,
  shuffle_time JSON NULL,
  discoveries JSON NULL,
  custom_info JSON NULL,
  vote_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_block (block),
  INDEX idx_difficulty (difficulty),
  INDEX idx_date (date)
) ENGINE=InnoDB;

CREATE TABLE votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_vote (log_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL,
  author_id INT NOT NULL,
  text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE log_media (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL,
  type ENUM('image', 'video') NOT NULL,
  url VARCHAR(500) NOT NULL,
  caption VARCHAR(255) NULL,
  position INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (log_id) REFERENCES logs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

