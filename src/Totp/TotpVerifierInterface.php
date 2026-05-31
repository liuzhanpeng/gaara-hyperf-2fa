<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Totp;

/**
 * TOTP 验证器接口.
 */
interface TotpVerifierInterface
{
    /**
     * 验证 TOTP 码是否有效.
     *
     * @param string $secret Base32 编码的 TOTP 密钥
     * @param string $code   用户提交的 6 位数码
     */
    public function verify(string $secret, string $code): bool;
}
