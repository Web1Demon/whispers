<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

class Pseudonym
{
    private const ADJECTIVES = [
        'Mystic', 'Silent', 'Cosmic', 'Shadow', 'Neon', 'Velvet', 'Astral', 'Hidden',
        'Crimson', 'Emerald', 'Silver', 'Golden', 'Lunar', 'Solar', 'Phantom', 'Ethereal',
        'Midnight', 'Curious', 'Quiet', 'Wandering', 'Echoing', 'Enchanted', 'Frost', 'Vivid',
        'Zen', 'Radiant', 'Stellar', 'Twilight', 'Humble', 'Brave', 'Gentle', 'Sapphire'
    ];

    private const NOUNS = [
        'Fox', 'Falcon', 'Badger', 'Wolf', 'Owl', 'Raven', 'Otter', 'Lynx',
        'Panda', 'Eagle', 'Dolphin', 'Hawk', 'Panther', 'Phoenix', 'Dragon', 'Nomad',
        'Voyager', 'Dreamer', 'Whisperer', 'Seeker', 'Echo', 'Spark', 'Oracle', 'Guardian',
        'Wanderer', 'Poet', 'Specter', 'Horizon', 'Comet', 'Cipher', 'Vagabond', 'Stargazer'
    ];

    private const AVATAR_PALETTES = [
        ['from' => '#6366f1', 'to' => '#a855f7'], // Indigo to Purple
        ['from' => '#3b82f6', 'to' => '#06b6d4'], // Blue to Cyan
        ['from' => '#ec4899', 'to' => '#f43f5e'], // Pink to Rose
        ['from' => '#10b981', 'to' => '#3b82f6'], // Emerald to Blue
        ['from' => '#f59e0b', 'to' => '#ef4444'], // Amber to Red
        ['from' => '#8b5cf6', 'to' => '#ec4899'], // Violet to Pink
        ['from' => '#14b8a6', 'to' => '#10b981'], // Teal to Emerald
        ['from' => '#f97316', 'to' => '#f59e0b'], // Orange to Amber
    ];

    private string $name;
    private string $avatarGradientFrom;
    private string $avatarGradientTo;

    public function __construct(string $name, string $avatarGradientFrom, string $avatarGradientTo)
    {
        $this->name = $name;
        $this->avatarGradientFrom = $avatarGradientFrom;
        $this->avatarGradientTo = $avatarGradientTo;
    }

    public static function generateFromSeed(string $seed): self
    {
        $hash = crc32($seed);
        $adjIndex = abs($hash) % count(self::ADJECTIVES);
        $nounIndex = abs(crc32($seed . '_noun')) % count(self::NOUNS);
        $numTag = str_pad((string)(abs($hash) % 10000), 4, '0', STR_PAD_LEFT);

        $name = self::ADJECTIVES[$adjIndex] . ' ' . self::NOUNS[$nounIndex] . ' #' . $numTag;
        $paletteIndex = abs($hash) % count(self::AVATAR_PALETTES);
        $palette = self::AVATAR_PALETTES[$paletteIndex];

        return new self($name, $palette['from'], $palette['to']);
    }

    public static function random(): self
    {
        return self::generateFromSeed(bin2hex(random_bytes(8)));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGradientFrom(): string
    {
        return $this->avatarGradientFrom;
    }

    public function getGradientTo(): string
    {
        return $this->avatarGradientTo;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'avatar_from' => $this->avatarGradientFrom,
            'avatar_to' => $this->avatarGradientTo,
        ];
    }
}
