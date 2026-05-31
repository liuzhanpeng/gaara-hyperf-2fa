<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\ChallengeStorage;

/**
 * 2FA 挑战存储接口.
 *
 * 负责持久化待验证挑战，并在 TOTP 验证阶段检索、删除挑战。
 */
interface ChallengeStorageInterface
{
    /**
     * 存储挑战.
     *
     * @param string           $challengeId 唯一挑战 ID
     * @param TwoFactorChallenge $challenge   挑战数据
     */
    public function store(string $challengeId, TwoFactorChallenge $challenge): void;

    /**
     * 获取挑战.
     *
     * 若不存在则返回 null。
     */
    public function get(string $challengeId): ?TwoFactorChallenge;

    /**
     * 删除挑战（验证通过或过期后调用）.
     */
    public function delete(string $challengeId): void;
}
