<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\User\UserInterface;

/**
 * 支持双因素认证的用户接口.
 *
 * 用户模型实现此接口后，即可参与 2FA 认证流程。
 * 具体的 2FA 方式由 getPreferredTwoFactorMethod() 决定，
 * 各方式对应专属子接口（TotpUserInterface、EmailOtpUserInterface 等）。
 */
interface TwoFactorAwareUserInterface extends UserInterface
{
    /**
     * 是否启用了双因素认证.
     */
    public function isTwoFactorEnabled(): bool;

    /**
     * 返回用户偏好的 2FA 方式类型标识.
     *
     * 内置值：'totp'、'email'
     * 可自定义扩展。
     */
    public function getPreferredTwoFactorMethod(): string;
}
