-- Whisper Application Database Schema (MySQL 8.0+ / MariaDB 10.4+)
-- Engine: InnoDB with utf8mb4 full unicode support

-- 1. POSTS TABLE
CREATE TABLE IF NOT EXISTS posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(64) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'general',
    author_pseudonym VARCHAR(100) NOT NULL,
    author_avatar_from VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    author_avatar_to VARCHAR(20) NOT NULL DEFAULT '#a855f7',
    fingerprint_hash VARCHAR(64) NOT NULL,
    ownership_hash VARCHAR(64) NOT NULL,
    likes_count INT UNSIGNED NOT NULL DEFAULT 0,
    comments_count INT UNSIGNED NOT NULL DEFAULT 0,
    views_count INT UNSIGNED NOT NULL DEFAULT 0,
    hot_score DOUBLE NOT NULL DEFAULT 0.0,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_posts_created_at (created_at DESC),
    INDEX idx_posts_hot_score (hot_score DESC, created_at DESC),
    INDEX idx_posts_likes_count (likes_count DESC),
    INDEX idx_posts_category (category, created_at DESC),
    INDEX idx_posts_fingerprint (fingerprint_hash),
    INDEX idx_posts_uid (uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. COMMENTS TABLE (Hierarchical Tree with Materialized Path)
CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    depth INT UNSIGNED NOT NULL DEFAULT 0,
    path VARCHAR(500) NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    author_pseudonym VARCHAR(100) NOT NULL,
    author_avatar_from VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    author_avatar_to VARCHAR(20) NOT NULL DEFAULT '#a855f7',
    fingerprint_hash VARCHAR(64) NOT NULL,
    ownership_hash VARCHAR(64) NOT NULL,
    likes_count INT UNSIGNED NOT NULL DEFAULT 0,
    replies_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_comments_post (post_id, created_at ASC),
    INDEX idx_comments_parent (parent_id),
    INDEX idx_comments_path (path),
    INDEX idx_comments_fingerprint (fingerprint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. POLYMORPHIC LIKES TABLE (Guaranteed Idempotency)
CREATE TABLE IF NOT EXISTS likes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('post', 'comment') NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    user_fingerprint VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like_user_target (target_type, target_id, user_fingerprint),
    INDEX idx_likes_target (target_type, target_id),
    INDEX idx_likes_user (user_fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. RATE LIMITS TABLE (Sliding Window Algorithm)
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_hash VARCHAR(64) NOT NULL,
    hit_time INT UNSIGNED NOT NULL,
    INDEX idx_rate_limits_key_time (key_hash, hit_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ANONYMOUS IDENTITIES TABLE
CREATE TABLE IF NOT EXISTS identities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    fingerprint VARCHAR(64) NOT NULL UNIQUE,
    pseudonym VARCHAR(100) NOT NULL,
    avatar_from VARCHAR(20) NOT NULL,
    avatar_to VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identities_fingerprint (fingerprint),
    INDEX idx_identities_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
