<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

/**
 * Email OTP 用户接口.
 *
 * 使用邮件动态码进行二次验证的用户需实现此接口。
 */
interface EmailOtpUserInterface extends TwoFactorAwareUserInterface
{
    /**
     * 返回接收 OTP 的邮箱地址.
     */
    public function getTwoFactorEmail(): string;
}
