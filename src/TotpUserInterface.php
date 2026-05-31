<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

/**
 * TOTP 用户接口.
 *
 * 使用 TOTP（如 Google Authenticator、Authy）进行二次验证的用户需实现此接口。
 */
interface TotpUserInterface extends TwoFactorAwareUserInterface
{
    /**
     * 返回 TOTP 密钥（Base32 编码）.
     */
    public function getTwoFactorSecret(): string;
}
