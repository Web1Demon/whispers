<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Core\Request;
use App\Domain\Entity\Identity;
use App\Domain\ValueObject\Pseudonym;

class IdentityManager
{
    private string $appSecret;

    public function __construct(string $appSecret)
    {
        $this->appSecret = $appSecret;
    }

    /**
     * Resolves or generates the Anonymous Identity from request headers or IP/Agent
     */
    public function resolveIdentity(Request $request): Identity
    {
        $token = $request->getAnonymousToken();

        if ($token !== null && trim($token) !== '' && strlen($token) >= 16) {
            $fingerprint = hash_hmac('sha256', $token, $this->appSecret);
            $pseudonymVO = Pseudonym::generateFromSeed($fingerprint);

            return new Identity(
                $token,
                $fingerprint,
                $pseudonymVO->getName(),
                $pseudonymVO->getGradientFrom(),
                $pseudonymVO->getGradientTo()
            );
        }

        // Fallback: derive token from IP + User Agent
        $clientIp = $request->getClientIp();
        $userAgent = $request->getUserAgent();
        $rawSeed = "{$clientIp}|{$userAgent}|" . date('Y-m'); // Monthly rotating salt
        $derivedToken = hash_hmac('sha256', $rawSeed, $this->appSecret);
        $fingerprint = hash_hmac('sha256', $derivedToken, $this->appSecret);
        $pseudonymVO = Pseudonym::generateFromSeed($fingerprint);

        return new Identity(
            $derivedToken,
            $fingerprint,
            $pseudonymVO->getName(),
            $pseudonymVO->getGradientFrom(),
            $pseudonymVO->getGradientTo()
        );
    }

    /**
     * Creates a new randomized anonymous identity
     */
    public function createNewIdentity(): Identity
    {
        $token = bin2hex(random_bytes(24));
        $fingerprint = hash_hmac('sha256', $token, $this->appSecret);
        $pseudonymVO = Pseudonym::generateFromSeed($fingerprint);

        return new Identity(
            $token,
            $fingerprint,
            $pseudonymVO->getName(),
            $pseudonymVO->getGradientFrom(),
            $pseudonymVO->getGradientTo()
        );
    }

    /**
     * Generates a tamper-proof cryptographic ownership token for an entity (post or comment)
     */
    public function generateOwnershipToken(string $entityType, string|int $entityId, string $fingerprint, string $createdAt): string
    {
        $payload = "{$entityType}:{$entityId}:{$fingerprint}:{$createdAt}";
        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    /**
     * Validates whether the given ownership token matches the entity's recorded ownership hash
     */
    public function verifyOwnership(string $providedToken, string $storedHash): bool
    {
        if (empty($providedToken) || empty($storedHash)) {
            return false;
        }

        return hash_equals($storedHash, $providedToken);
    }
}
