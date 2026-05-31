<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\SmsOtpUserInterface;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;

/**
 * SMS OTP 二次验证方式.
 *
 * 流程：
 *   1. initChallenge: 生成随机数字码 → 调用 SmsCodeSenderInterface 发送短信
 *      → 将码的 SHA-256 哈希存入 metadata（服务器不留明文）
 *      → 响应 data 中附加手机号 hint（如 138****5678）
 *   2. verify: 对用户提交的码做同样哈希后与 metadata 比对
 *
 * 安全说明：
 *   - 码为一次性使用：通过后立即删除 challenge（由 TwoFactorAuthenticator 负责）
 *   - TTL 由 ChallengeStorage 保证（session/redis 均设置过期时间）
 */
class SmsOtpMethod implements TwoFactorMethodInterface
{
    public function __construct(
        private SmsCodeSenderInterface $sender,
        private int $codeLength = 6,
    ) {
    }

    public function type(): string
    {
        return 'sms';
    }

    public function supports(TwoFactorAwareUserInterface $user): bool
    {
        return $user instanceof SmsOtpUserInterface;
    }

    public function initChallenge(TwoFactorAwareUserInterface $user): array
    {
        $code = $this->generateCode();

        /* @var SmsOtpUserInterface $user */
        $this->sender->send($user, $code);

        return [
            'metadata' => [
                'code_hash' => hash('sha256', $code),
            ],
            'response' => [
                'hint' => $this->maskPhone($user->getTwoFactorPhoneNumber()),
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
     * 对手机号做脱敏处理，例如 13812345678 → 138****5678.
     */
    private function maskPhone(string $phone): string
    {
        $len = mb_strlen($phone);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }

        // 保留前 3 位和后 4 位，中间替换为星号
        $prefix = mb_substr($phone, 0, 3);
        $suffix = mb_substr($phone, -4);
        $stars = str_repeat('*', max(1, $len - 7));

        return $prefix . $stars . $suffix;
    }
}
