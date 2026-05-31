<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\ChallengeStorage;

/**
 * 2FA 挑战数据.
 *
 * 存储待验证挑战的关联信息，用于在 TOTP 验证阶段恢复用户上下文。
 */
class TwoFactorChallenge
{
    public function __construct(
        public readonly string $userIdentifier,
        public readonly string $guardName,
        public readonly int $issuedAt,
    ) {
    }

    public function isExpired(int $ttl): bool
    {
        return (time() - $this->issuedAt) > $ttl;
    }
}
