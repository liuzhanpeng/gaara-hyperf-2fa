<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\ChallengeStorage;

use Hyperf\Redis\Redis;

/**
 * 基于 Redis 的 2FA 挑战存储.
 *
 * 适合 API/无状态场景，挑战 TTL 由 Redis 自动管理。
 *
 * 安装依赖：composer require hyperf/redis
 */
class RedisChallengeStorage implements ChallengeStorageInterface
{
    private const KEY_PREFIX = 'gaara:2fa:challenge:';

    public function __construct(
        private Redis $redis,
        private int $ttl = 300,
    ) {
    }

    public function store(string $challengeId, TwoFactorChallenge $challenge): void
    {
        $this->redis->setex(
            self::KEY_PREFIX . $challengeId,
            $this->ttl,
            json_encode([
                'user_identifier' => $challenge->userIdentifier,
                'guard_name'      => $challenge->guardName,
                'method'          => $challenge->method,
                'issued_at'       => $challenge->issuedAt,
                'metadata'        => $challenge->metadata,
            ])
        );
    }

    public function get(string $challengeId): ?TwoFactorChallenge
    {
        $raw = $this->redis->get(self::KEY_PREFIX . $challengeId);
        if (! is_string($raw)) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)
            || ! isset($data['user_identifier'], $data['guard_name'], $data['method'], $data['issued_at'])
        ) {
            return null;
        }

        return new TwoFactorChallenge(
            $data['user_identifier'],
            $data['guard_name'],
            $data['method'],
            (int) $data['issued_at'],
            $data['metadata'] ?? [],
        );
    }

    public function delete(string $challengeId): void
    {
        $this->redis->del(self::KEY_PREFIX . $challengeId);
    }
}
