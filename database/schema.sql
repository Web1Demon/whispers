-- Whisper Database Schema (SQLite 3.35+)
-- High Performance, Concurrent Safe, Normalized Schema

PRAGMA foreign_keys = ON;

-- 1. POSTS TABLE
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'general',
    author_pseudonym TEXT NOT NULL,
    author_avatar_from TEXT NOT NULL DEFAULT '#6366f1',
    author_avatar_to TEXT NOT NULL DEFAULT '#a855f7',
    fingerprint_hash TEXT NOT NULL,
    ownership_hash TEXT NOT NULL,
    likes_count INTEGER NOT NULL DEFAULT 0,
    comments_count INTEGER NOT NULL DEFAULT 0,
    views_count INTEGER NOT NULL DEFAULT 0,
    hot_score REAL NOT NULL DEFAULT 0.0,
    is_pinned INTEGER NOT NULL DEFAULT 0,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_posts_created_at ON posts(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_posts_hot_score ON posts(hot_score DESC, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_posts_likes_count ON posts(likes_count DESC);
CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_posts_fingerprint ON posts(fingerprint_hash);
CREATE INDEX IF NOT EXISTS idx_posts_uid ON posts(uid);

-- 2. COMMENTS TABLE (Adjacency List + Materialized Path)
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    parent_id INTEGER NULL,
    depth INTEGER NOT NULL DEFAULT 0,
    path TEXT NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    author_pseudonym TEXT NOT NULL,
    author_avatar_from TEXT NOT NULL DEFAULT '#6366f1',
    author_avatar_to TEXT NOT NULL DEFAULT '#a855f7',
    fingerprint_hash TEXT NOT NULL,
    ownership_hash TEXT NOT NULL,
    likes_count INTEGER NOT NULL DEFAULT 0,
    replies_count INTEGER NOT NULL DEFAULT 0,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id, created_at ASC);
CREATE INDEX IF NOT EXISTS idx_comments_parent_id ON comments(parent_id);
CREATE INDEX IF NOT EXISTS idx_comments_path ON comments(path);
CREATE INDEX IF NOT EXISTS idx_comments_fingerprint ON comments(fingerprint_hash);

-- 3. POLYMORPHIC LIKES TABLE
CREATE TABLE IF NOT EXISTS likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL CHECK (target_type IN ('post', 'comment')),
    target_id INTEGER NOT NULL,
    user_fingerprint TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (target_type, target_id, user_fingerprint)
);

CREATE INDEX IF NOT EXISTS idx_likes_target ON likes(target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_likes_user ON likes(user_fingerprint);

-- 4. RATE LIMITS TABLE (Sliding window / Token bucket)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key_hash TEXT NOT NULL,
    hit_time INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_rate_limits_key_time ON rate_limits(key_hash, hit_time);

-- 5. ANONYMOUS IDENTITIES TABLE
CREATE TABLE IF NOT EXISTS identities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL UNIQUE,
    fingerprint TEXT NOT NULL UNIQUE,
    pseudonym TEXT NOT NULL,
    avatar_from TEXT NOT NULL,
    avatar_to TEXT NOT NULL,
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_identities_fingerprint ON identities(fingerprint);
CREATE INDEX IF NOT EXISTS idx_identities_token ON identities(token);
