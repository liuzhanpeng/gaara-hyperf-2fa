<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\Token\TokenInterface;

/**
 * 双因素认证待验证令牌.
 *
 * 当用户通过第一阶段认证（账号/密码）但尚未完成 TOTP 验证时持有此令牌。
 * 此令牌不是 AuthenticatedToken，因此 Guard::isTokenAuthenticated() 返回 false，
 * 防止用户在未完成 2FA 前访问受保护资源。
 */
class TwoFactorPendingToken implements TokenInterface
{
    public function __construct(
        private string $guardName,
        private string $userIdentifier,
        private string $challengeId,
    ) {
    }

    public function __toString(): string
    {
        return sprintf(
            '%s(guard=%s, user=%s, challenge=%s)',
            static::class,
            $this->guardName,
            $this->userIdentifier,
            $this->challengeId,
        );
    }

    public function __serialize(): array
    {
        return [
            'guard_name' => $this->guardName,
            'user_identifier' => $this->userIdentifier,
            'challenge_id' => $this->challengeId,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->guardName = $data['guard_name'];
        $this->userIdentifier = $data['user_identifier'];
        $this->challengeId = $data['challenge_id'];
    }

    public function getGuardName(): string
    {
        return $this->guardName;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getChallengeId(): string
    {
        return $this->challengeId;
    }

    public function hasAttribute(string $name): bool
    {
        return false;
    }

    public function getAttribute(string $name): mixed
    {
        return null;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        // pending token carries no mutable attributes
    }
}
