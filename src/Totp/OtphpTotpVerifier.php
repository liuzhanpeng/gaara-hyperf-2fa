<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Totp;

use OTPHP\TOTP;

/**
 * 基于 spomky-labs/otphp 的 TOTP 验证器.
 *
 * 支持标准的 RFC 6238 TOTP 算法（兼容 Google Authenticator、Authy 等）。
 *
 * 安装依赖：composer require spomky-labs/otphp
 */
class OtphpTotpVerifier implements TotpVerifierInterface
{
    /**
     * @param int $leeway 允许的时间偏差（秒），用于容忍客户端与服务器时钟不同步
     */
    public function __construct(
        private int $leeway = 30,
    ) {
    }

    public function verify(string $secret, string $code): bool
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setParameter('leeway', $this->leeway);

        return $totp->verify($code);
    }
}
