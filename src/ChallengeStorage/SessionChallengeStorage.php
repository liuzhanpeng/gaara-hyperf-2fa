<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\ChallengeStorage;

use Hyperf\Contract\SessionInterface;

/**
 * 基于 Session 的 2FA 挑战存储.
 *
 * 适合传统 Web 场景（Session 有状态认证流程）。
 */
class SessionChallengeStorage implements ChallengeStorageInterface
{
    private const SESSION_PREFIX = '_gaara_2fa_challenge_';

    public function __construct(
        private SessionInterface $session,
    ) {
    }

    public function store(string $challengeId, TwoFactorChallenge $challenge): void
    {
        $this->session->set(
            self::SESSION_PREFIX . $challengeId,
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
        $raw = $this->session->get(self::SESSION_PREFIX . $challengeId);
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
        $this->session->remove(self::SESSION_PREFIX . $challengeId);
    }
}
