<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\ChallengeStorage;

/**
 * 2FA 挑战数据.
 *
 * 存储待验证挑战的关联信息，用于在验证阶段恢复用户上下文。
 *
 * @property array<string, mixed> $metadata 各方式存储的额外数据
 *   - TOTP: 空数组
 *   - Email OTP: ['code_hash' => sha256(code)]
 */
class TwoFactorChallenge
{
    public function __construct(
        public readonly string $userIdentifier,
        public readonly string $guardName,
        public readonly string $method,
        public readonly int $issuedAt,
        public readonly array $metadata = [],
    ) {
    }

    public function isExpired(int $ttl): bool
    {
        return (time() - $this->issuedAt) > $ttl;
    }
}
