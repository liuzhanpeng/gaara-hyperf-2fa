<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\EmailOtpUserInterface;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;

/**
 * Email OTP 二次验证方式.
 *
 * 流程：
 *   1. initChallenge: 生成随机数字码 → 调用 EmailCodeSenderInterface 发送邮件
 *      → 将码的 SHA-256 哈希存入 metadata（服务器不留明文）
 *      → 响应 data 中附加邮箱 hint（如 u**r@e*****e.com）
 *   2. verify: 对用户提交的码做同样哈希后与 metadata 比对
 *
 * 安全说明：
 *   - 码为一次性使用：通过后立即删除 challenge（由 TwoFactorAuthenticator 负责）
 *   - TTL 由 ChallengeStorage 保证（session/redis 均设置过期时间）
 */
class EmailOtpMethod implements TwoFactorMethodInterface
{
    public function __construct(
        private EmailCodeSenderInterface $sender,
        private int $codeLength = 6,
    ) {
    }

    public function type(): string
    {
        return 'email';
    }

    public function supports(TwoFactorAwareUserInterface $user): bool
    {
        return $user instanceof EmailOtpUserInterface;
    }

    public function initChallenge(TwoFactorAwareUserInterface $user): array
    {
        $code = $this->generateCode();

        /** @var EmailOtpUserInterface $user */
        $this->sender->send($user, $code);

        return [
            'metadata' => [
                'code_hash' => hash('sha256', $code),
            ],
            'response' => [
                'hint' => $this->maskEmail($user->getTwoFactorEmail()),
            ],
        ];
    }

    public function verify(TwoFactorAwareUserInterface $user, string $code, TwoFactorChallenge $challenge): bool
    {
        $storedHash = $challenge->metadata['code_hash'] ?? null;
        if (! is_string($storedHash)) {
            return false;
        }

        return hash_equals($storedHash, hash('sha256', $code));
    }

    private function generateCode(): string
    {
        $max = (int) str_repeat('9', $this->codeLength);
        $code = random_int(0, $max);

        return str_pad((string) $code, $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * 对邮箱做脱敏处理，例如 user@example.com → u**r@e*****e.com.
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + ['', ''];

        $mask = static function (string $str): string {
            $len = mb_strlen($str);
            if ($len <= 2) {
                return $str;
            }
            return mb_substr($str, 0, 1)
                . str_repeat('*', max(1, $len - 2))
                . mb_substr($str, -1);
        };

        $domainParts = explode('.', $domain, 2);
        $maskedDomain = $mask($domainParts[0]) . (isset($domainParts[1]) ? '.' . $domainParts[1] : '');

        return $mask($local) . '@' . $maskedDomain;
    }
}
