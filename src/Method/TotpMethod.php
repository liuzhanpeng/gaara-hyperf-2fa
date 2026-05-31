<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\Totp\TotpVerifierInterface;
use GaaraHyperf\TwoFactor\TotpUserInterface;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;

/**
 * TOTP 二次验证方式.
 *
 * 基于 RFC 6238 时间型一次性密码，兼容 Google Authenticator、Authy 等主流 App。
 * TOTP 码由用户端 App 生成，服务器无需在 initChallenge 阶段做任何操作。
 */
class TotpMethod implements TwoFactorMethodInterface
{
    public function __construct(
        private TotpVerifierInterface $verifier,
    ) {
    }

    public function type(): string
    {
        return 'totp';
    }

    public function supports(TwoFactorAwareUserInterface $user): bool
    {
        return $user instanceof TotpUserInterface;
    }

    /**
     * TOTP 无需服务器主动发送，直接返回空结果.
     */
    public function initChallenge(TwoFactorAwareUserInterface $user): array
    {
        return [
            'metadata' => [],
            'response' => [],
        ];
    }

    public function verify(TwoFactorAwareUserInterface $user, string $code, TwoFactorChallenge $challenge): bool
    {
        if (! $user instanceof TotpUserInterface) {
            return false;
        }

        return $this->verifier->verify($user->getTwoFactorSecret(), $code);
    }
}
