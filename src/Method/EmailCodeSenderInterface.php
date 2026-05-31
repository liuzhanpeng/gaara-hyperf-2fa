<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\EmailOtpUserInterface;

/**
 * Email OTP 发送器接口.
 *
 * 应用层实现此接口以集成自己的邮件服务（Hyperf Mailer、SwiftMailer 等）。
 */
interface EmailCodeSenderInterface
{
    /**
     * 向用户发送 OTP 验证码邮件.
     *
     * @param EmailOtpUserInterface $user 接收邮件的用户
     * @param string                $code 明文验证码
     */
    public function send(EmailOtpUserInterface $user, string $code): void;
}
