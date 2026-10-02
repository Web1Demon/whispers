<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Core\Exception\ValidationException;

class ContentModerator
{
    private array $config;

    private const BLOCKED_WORDS = [
        'badwordplaceholder1', 'spamattack123', 'buyviagraonline'
    ];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function sanitize(string $input): string
    {
        // 1. Strip harmful control characters
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
        
        // 2. Normalize unicode & newlines
        $clean = str_replace(["\r\n", "\r"], "\n", (string)$clean);
        
        // 3. Trim extra whitespace
        return trim((string)$clean);
    }

    public function validatePost(string $title, string $content, string $category): array
    {
        $errors = [];
        $title = $this->sanitize($title);
        $content = $this->sanitize($content);
        $category = strtolower($this->sanitize($category));

        $minTitle = $this->config['min_title_length'] ?? 3;
        $maxTitle = $this->config['max_title_length'] ?? 120;
        $minContent = $this->config['min_post_length'] ?? 5;
        $maxContent = $this->config['max_post_length'] ?? 3000;

        if (mb_strlen($title) < $minTitle) {
            $errors['title'] = "Title must be at least {$minTitle} characters long.";
        } elseif (mb_strlen($title) > $maxTitle) {
            $errors['title'] = "Title cannot exceed {$maxTitle} characters.";
        }

        if (mb_strlen($content) < $minContent) {
            $errors['content'] = "Content must be at least {$minContent} characters long.";
        } elseif (mb_strlen($content) > $maxContent) {
            $errors['content'] = "Content cannot exceed {$maxContent} characters.";
        }

        if (empty($category) || mb_strlen($category) > 50) {
            $errors['category'] = "Category must be between 1 and 50 characters.";
        }

        // Check for spam link patterns
        $linkCount = preg_match_all('/https?:\/\//i', $content);
        if ($linkCount > 4) {
            $errors['content'] = "Too many external links detected. Possible spam.";
        }

        if (!empty($errors)) {
            throw new ValidationException("Validation failed for post submission.", $errors);
        }

        return [
            'title' => $title,
            'content' => $content,
            'category' => $category,
        ];
    }

    public function validateComment(string $content): string
    {
        $clean = $this->sanitize($content);
        $minLen = $this->config['min_comment_length'] ?? 1;
        $maxLen = $this->config['max_comment_length'] ?? 1500;

        $errors = [];
        if (mb_strlen($clean) < $minLen) {
            $errors['content'] = "Comment cannot be empty.";
        } elseif (mb_strlen($clean) > $maxLen) {
            $errors['content'] = "Comment cannot exceed {$maxLen} characters.";
        }

        if (!empty($errors)) {
            throw new ValidationException("Validation failed for comment submission.", $errors);
        }

        return $clean;
    }
}
