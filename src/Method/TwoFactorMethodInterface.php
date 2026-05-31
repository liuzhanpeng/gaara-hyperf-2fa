<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;

/**
 * 2FA 方式接口.
 *
 * 每种二次验证方式（TOTP、Email OTP、SMS OTP 等）实现此接口。
 */
interface TwoFactorMethodInterface
{
    /**
     * 返回方式类型标识（与 TwoFactorAwareUserInterface::getPreferredTwoFactorMethod() 对应）.
     *
     * 例如：'totp'、'email'、'sms'
     */
    public function type(): string;

    /**
     * 判断当前方式是否支持该用户.
     *
     * 通常检查用户是否实现了对应的子接口（TotpUserInterface 等）。
     */
    public function supports(TwoFactorAwareUserInterface $user): bool;

    /**
     * 初始化挑战（在挑战被存储之前调用）.
     *
     * TOTP：无需操作，返回空数组。
     * Email OTP：生成随机码并发送邮件，将码的哈希存入 metadata，返回响应附加信息（如 hint）。
     *
     * @return array{
     *   metadata: array<string, mixed>,
     *   response: array<string, mixed>
     * }
     * - metadata: 存储到 TwoFactorChallenge->metadata，供 verify() 使用
     * - response: 合并进响应体的 data 字段，用于向客户端传递附加信息（如邮箱 hint）
     */
    public function initChallenge(TwoFactorAwareUserInterface $user): array;

    /**
     * 验证用户提交的码.
     *
     * @param string            $code      用户提交的验证码
     * @param TwoFactorChallenge $challenge 存储的挑战数据（含 metadata）
     */
    public function verify(TwoFactorAwareUserInterface $user, string $code, TwoFactorChallenge $challenge): bool;
}
