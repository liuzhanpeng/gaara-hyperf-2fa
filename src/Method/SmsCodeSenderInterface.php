<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\SmsOtpUserInterface;

/**
 * SMS OTP 发送器接口.
 *
 * 应用层实现此接口以集成自己的短信服务（阿里云 SMS、腾讯云 SMS 等）。
 */
interface SmsCodeSenderInterface
{
    /**
     * 向用户发送 OTP 验证码短信.
     *
     * @param SmsOtpUserInterface $user 接收短信的用户
     * @param string $code 明文验证码
     */
    public function send(SmsOtpUserInterface $user, string $code): void;
}
