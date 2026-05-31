<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\EmailOtpUserInterface;
use GaaraHyperf\TwoFactor\SmsOtpUserInterface;
use GaaraHyperf\TwoFactor\TotpUserInterface;
use GaaraHyperf\User\UserInterface;
use Mockery\MockInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

afterEach(function (): void {
    Mockery::close();
});

function makeRequest(string $method = 'GET', string $path = '/', array $body = []): ServerRequestInterface
{
    /** @var MockInterface&ServerRequestInterface $request */
    $request = Mockery::mock(ServerRequestInterface::class);
    /** @var MockInterface&UriInterface $uri */
    $uri = Mockery::mock(UriInterface::class);

    $request->shouldReceive('getMethod')->andReturn($method);
    $request->shouldReceive('getUri')->andReturn($uri);
    $uri->shouldReceive('getPath')->andReturn($path);

    if (! empty($body)) {
        $request->shouldReceive('getParsedBody')->andReturn($body);
    }

    return $request;
}

/**
 * 创建实现 TotpUserInterface 的 mock 用户.
 */
function makeTwoFactorUser(string $id = 'user-1', bool $enabled = true, string $secret = 'BASE32SECRET'): TotpUserInterface
{
    /** @var MockInterface&TotpUserInterface $user */
    $user = Mockery::mock(TotpUserInterface::class);
    $user->shouldReceive('getIdentifier')->andReturn($id);
    $user->shouldReceive('isTwoFactorEnabled')->andReturn($enabled);
    $user->shouldReceive('getPreferredTwoFactorMethod')->andReturn('totp');
    $user->shouldReceive('getTwoFactorSecret')->andReturn($secret);
    return $user;
}

/**
 * 创建实现 EmailOtpUserInterface 的 mock 用户.
 */
function makeEmailOtpUser(string $id = 'user-1', bool $enabled = true, string $email = 'user@example.com'): EmailOtpUserInterface
{
    /** @var EmailOtpUserInterface&MockInterface $user */
    $user = Mockery::mock(EmailOtpUserInterface::class);
    $user->shouldReceive('getIdentifier')->andReturn($id);
    $user->shouldReceive('isTwoFactorEnabled')->andReturn($enabled);
    $user->shouldReceive('getPreferredTwoFactorMethod')->andReturn('email');
    $user->shouldReceive('getTwoFactorEmail')->andReturn($email);
    return $user;
}

function makeRegularUser(string $id = 'user-1'): UserInterface
{
    /** @var MockInterface&UserInterface $user */
    $user = Mockery::mock(UserInterface::class);
    $user->shouldReceive('getIdentifier')->andReturn($id);
    return $user;
}

/**
 * 创建实现 SmsOtpUserInterface 的 mock 用户.
 */
function makeSmsOtpUser(string $id = 'user-1', bool $enabled = true, string $phone = '13812345678'): SmsOtpUserInterface
{
    /** @var MockInterface&SmsOtpUserInterface $user */
    $user = Mockery::mock(SmsOtpUserInterface::class);
    $user->shouldReceive('getIdentifier')->andReturn($id);
    $user->shouldReceive('isTwoFactorEnabled')->andReturn($enabled);
    $user->shouldReceive('getPreferredTwoFactorMethod')->andReturn('sms');
    $user->shouldReceive('getTwoFactorPhoneNumber')->andReturn($phone);
    return $user;
}
