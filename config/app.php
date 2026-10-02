<?php

declare(strict_types=1);

return [
    'name' => 'Whisper - Anonymous Messaging Platform',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => (bool)(getenv('APP_DEBUG') ?: true),
    'url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'version' => '1.0.0',

    // Anonymous Hashing Salt/Pepper (protects user privacy and guarantees uniform pseudonym distribution)
    'app_secret' => getenv('APP_SECRET') ?: 'whisper_super_secret_cryptographic_salt_948271',

    // Rate Limiting Rules (Hits per interval in seconds)
    'rate_limits' => [
        'posts' => [
            'max_attempts' => 10,
            'decay_seconds' => 60,
        ],
        'comments' => [
            'max_attempts' => 30,
            'decay_seconds' => 60,
        ],
        'likes' => [
            'max_attempts' => 100,
            'decay_seconds' => 60,
        ],
        'global' => [
            'max_attempts' => 200,
            'decay_seconds' => 60,
        ],
    ],

    // Content Moderation & Constraints
    'moderation' => [
        'min_post_length' => 5,
        'max_post_length' => 3000,
        'min_title_length' => 3,
        'max_title_length' => 120,
        'min_comment_length' => 1,
        'max_comment_length' => 1500,
        'max_comment_depth' => 10, // Prevent infinite nested replies
    ],

    'cache_dir' => __DIR__ . '/../storage/cache',
];
