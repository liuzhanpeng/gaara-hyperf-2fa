<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\User\UserInterface;

/**
 * 支持双因素认证的用户接口.
 *
 * 用户模型实现此接口后，即可参与2FA认证流程。
 * 只有当 isTwoFactorEnabled() 返回 true 时，才会强制要求 TOTP 验证。
 */
interface TwoFactorAwareUserInterface extends UserInterface
{
    /**
     * 是否启用了双因素认证.
     */
    public function isTwoFactorEnabled(): bool;

    /**
     * 返回 TOTP 密钥（Base32 编码）.
     */
    public function getTwoFactorSecret(): string;
}
