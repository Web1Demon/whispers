<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class Identity
{
    private string $token;
    private string $fingerprint;
    private string $pseudonym;
    private string $avatarGradientFrom;
    private string $avatarGradientTo;
    private string $createdAt;

    public function __construct(
        string $token,
        string $fingerprint,
        string $pseudonym,
        string $avatarGradientFrom,
        string $avatarGradientTo,
        ?string $createdAt = null
    ) {
        $this->token = $token;
        $this->fingerprint = $fingerprint;
        $this->pseudonym = $pseudonym;
        $this->avatarGradientFrom = $avatarGradientFrom;
        $this->avatarGradientTo = $avatarGradientTo;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
    }

    public function toArray(bool $includePrivate = false): array
    {
        $data = [
            'fingerprint' => $this->fingerprint,
            'pseudonym' => $this->pseudonym,
            'avatar_gradient' => [
                'from' => $this->avatarGradientFrom,
                'to' => $this->avatarGradientTo,
            ],
            'created_at' => $this->createdAt,
        ];

        if ($includePrivate) {
            $data['token'] = $this->token;
        }

        return $data;
    }

    public function getToken(): string { return $this->token; }
    public function getFingerprint(): string { return $this->fingerprint; }
    public function getPseudonym(): string { return $this->pseudonym; }
    public function getAvatarGradientFrom(): string { return $this->avatarGradientFrom; }
    public function getAvatarGradientTo(): string { return $this->avatarGradientTo; }
    public function getCreatedAt(): string { return $this->createdAt; }
}
