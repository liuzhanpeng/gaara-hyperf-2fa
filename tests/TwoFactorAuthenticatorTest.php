<?php

declare(strict_types=1);

use GaaraHyperf\Exception\AuthenticationException;
use GaaraHyperf\TwoFactor\ChallengeStorage\ChallengeStorageInterface;
use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodInterface;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodRegistry;
use GaaraHyperf\TwoFactor\TwoFactorAuthenticator;
use GaaraHyperf\UserProvider\UserProviderInterface;

function makeRegistry(?TwoFactorMethodInterface $method = null, string $type = 'totp'): TwoFactorMethodRegistry
{
    $registry = new TwoFactorMethodRegistry();
    if ($method !== null) {
        $registry->register($method);
    }
    return $registry;
}

function makeMethodMock(string $type, bool $verifyResult): TwoFactorMethodInterface
{
    $method = Mockery::mock(TwoFactorMethodInterface::class);
    $method->shouldReceive('type')->andReturn($type);
    $method->shouldReceive('verify')->andReturn($verifyResult);
    return $method;
}

function makeAuthenticator(
    ?ChallengeStorageInterface $storage = null,
    ?TwoFactorMethodRegistry $registry = null,
    ?UserProviderInterface $userProvider = null,
    string $verifyPath = '/two-factor/verify',
    int $ttl = 300,
): TwoFactorAuthenticator {
    return new TwoFactorAuthenticator(
        verifyPath: $verifyPath,
        challengeStorage: $storage ?? Mockery::mock(ChallengeStorageInterface::class),
        methodRegistry: $registry ?? new TwoFactorMethodRegistry(),
        userProvider: $userProvider ?? Mockery::mock(UserProviderInterface::class),
        challengeTtl: $ttl,
        codeField: 'code',
        challengeIdField: 'challenge_id',
    );
}

it('supports POST requests on the verify path', function (): void {
    $authenticator = makeAuthenticator();
    $request = makeRequest('POST', '/two-factor/verify');
    expect($authenticator->supports($request))->toBeTrue();
});

it('does not support GET requests', function (): void {
    $authenticator = makeAuthenticator();
    $request = makeRequest('GET', '/two-factor/verify');
    expect($authenticator->supports($request))->toBeFalse();
});

it('does not support different paths', function (): void {
    $authenticator = makeAuthenticator();
    $request = makeRequest('POST', '/other/path');
    expect($authenticator->supports($request))->toBeFalse();
});

it('is interactive', function (): void {
    $authenticator = makeAuthenticator();
    expect($authenticator->isInteractive())->toBeTrue();
});

it('throws when challenge_id is missing from request body', function (): void {
    $authenticator = makeAuthenticator();
    $request = makeRequest('POST', '/two-factor/verify', ['code' => '123456']);

    expect(fn () => $authenticator->authenticate($request))
        ->toThrow(AuthenticationException::class, 'Missing challenge_id');
});

it('throws when code is missing from request body', function (): void {
    $authenticator = makeAuthenticator();
    $request = makeRequest('POST', '/two-factor/verify', ['challenge_id' => 'abc']);

    expect(fn () => $authenticator->authenticate($request))
        ->toThrow(AuthenticationException::class, 'Missing two-factor code');
});

it('throws when challenge does not exist in storage', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $storage->shouldReceive('get')->with('nonexistent')->andReturn(null);

    $authenticator = makeAuthenticator(storage: $storage);
    $request = makeRequest('POST', '/two-factor/verify', [
        'challenge_id' => 'nonexistent',
        'code' => '123456',
    ]);

    expect(fn () => $authenticator->authenticate($request))
        ->toThrow(AuthenticationException::class, 'Invalid or expired challenge');
});

it('throws and deletes challenge when it is expired', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $challenge = new TwoFactorChallenge('user-1', 'api', 'totp', time() - 400);
    $storage->shouldReceive('get')->with('abc')->andReturn($challenge);
    $storage->shouldReceive('delete')->with('abc')->once();

    $authenticator = makeAuthenticator(storage: $storage, ttl: 300);
    $request = makeRequest('POST', '/two-factor/verify', [
        'challenge_id' => 'abc',
        'code' => '123456',
    ]);

    expect(fn () => $authenticator->authenticate($request))
        ->toThrow(AuthenticationException::class, 'expired');
});

it('throws when 2FA code is invalid', function (): void {
    $user = makeTwoFactorUser('user-1', true, 'SECRET');

    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $challenge = new TwoFactorChallenge('user-1', 'api', 'totp', time());
    $storage->shouldReceive('get')->with('abc')->andReturn($challenge);

    $userProvider = Mockery::mock(UserProviderInterface::class);
    $userProvider->shouldReceive('findByIdentifier')->with('user-1')->andReturn($user);

    $method = makeMethodMock('totp', false);
    $registry = makeRegistry($method);

    $authenticator = makeAuthenticator(storage: $storage, registry: $registry, userProvider: $userProvider);
    $request = makeRequest('POST', '/two-factor/verify', [
        'challenge_id' => 'abc',
        'code' => 'wrong',
    ]);

    expect(fn () => $authenticator->authenticate($request))
        ->toThrow(AuthenticationException::class, 'Invalid two-factor code');
});

it('returns a valid passport when 2FA code is correct', function (): void {
    $user = makeTwoFactorUser('user-1', true, 'SECRET');

    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $challenge = new TwoFactorChallenge('user-1', 'api', 'totp', time());
    $storage->shouldReceive('get')->with('abc')->andReturn($challenge);
    $storage->shouldReceive('delete')->with('abc')->once();

    $userProvider = Mockery::mock(UserProviderInterface::class);
    $userProvider->shouldReceive('findByIdentifier')->with('user-1')->andReturn($user);

    $method = makeMethodMock('totp', true);
    $registry = makeRegistry($method);

    $authenticator = makeAuthenticator(storage: $storage, registry: $registry, userProvider: $userProvider);
    $request = makeRequest('POST', '/two-factor/verify', [
        'challenge_id' => 'abc',
        'code' => '123456',
    ]);

    $passport = $authenticator->authenticate($request);

    expect($passport->getUserIdentifier())->toBe('user-1')
        ->and($passport->getUser())->toBe($user);
});
