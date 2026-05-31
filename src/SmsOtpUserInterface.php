<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

/**
 * SMS OTP 用户接口.
 *
 * 使用短信动态码进行二次验证的用户需实现此接口。
 */
interface SmsOtpUserInterface extends TwoFactorAwareUserInterface
{
    /**
     * 返回接收 OTP 短信的手机号（E.164 或本地格式，由 SmsCodeSenderInterface 实现决定）.
     */
    public function getTwoFactorPhoneNumber(): string;
}
