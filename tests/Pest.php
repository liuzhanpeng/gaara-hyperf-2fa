<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;
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

function makeTwoFactorUser(string $id = 'user-1', bool $enabled = true, string $secret = 'BASE32SECRET'): TwoFactorAwareUserInterface
{
    /** @var MockInterface&TwoFactorAwareUserInterface $user */
    $user = Mockery::mock(TwoFactorAwareUserInterface::class);
    $user->shouldReceive('getIdentifier')->andReturn($id);
    $user->shouldReceive('isTwoFactorEnabled')->andReturn($enabled);
    $user->shouldReceive('getTwoFactorSecret')->andReturn($secret);
    return $user;
}

function makeRegularUser(string $id = 'user-1'): UserInterface
{
    /** @var MockInterface&UserInterface $user */
    $user = Mockery::mock(UserInterface::class);
    $user->shouldReceive('getIdentifier')->andReturn($id);
    return $user;
}
