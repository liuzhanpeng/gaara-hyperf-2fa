<?php

declare(strict_types=1);

use GaaraHyperf\Authenticator\AuthenticatorInterface;
use GaaraHyperf\Event\AuthenticationSuccessEvent;
use GaaraHyperf\Passport\Passport;
use GaaraHyperf\Token\AuthenticatedToken;
use GaaraHyperf\TwoFactor\ChallengeStorage\ChallengeStorageInterface;
use GaaraHyperf\TwoFactor\EventListener\TwoFactorEnforcementListener;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodInterface;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodRegistry;
use GaaraHyperf\TwoFactor\TwoFactorAuthenticator;
use GaaraHyperf\TwoFactor\TwoFactorPendingToken;
use Psr\Http\Message\ServerRequestInterface;

function makeSuccessEvent(
    object $authenticator,
    object $user,
    string $guardName = 'api',
): AuthenticationSuccessEvent {
    $token = new AuthenticatedToken($guardName, $user->getIdentifier());
    $passport = new Passport($user->getIdentifier(), fn () => $user);
    $request = Mockery::mock(ServerRequestInterface::class);

    return new AuthenticationSuccessEvent($guardName, $authenticator, $token, $passport, $request, null, null);
}

function makeTotpRegistry(): array
{
    $method = Mockery::mock(TwoFactorMethodInterface::class);
    $method->shouldReceive('type')->andReturn('totp');
    $method->shouldReceive('initChallenge')->andReturn(['metadata' => [], 'response' => []]);

    $registry = new TwoFactorMethodRegistry();
    $registry->register($method);

    return [$registry, $method];
}

it('replaces the token with TwoFactorPendingToken when user has 2FA enabled', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $storage->shouldReceive('store')->once();

    [$registry] = makeTotpRegistry();
    $listener = new TwoFactorEnforcementListener($storage, $registry);
    $authenticator = Mockery::mock(AuthenticatorInterface::class);

    $user = makeTwoFactorUser('user-1', true);
    $event = makeSuccessEvent($authenticator, $user);

    $listener->onAuthenticationSuccess($event);

    expect($event->getToken())->toBeInstanceOf(TwoFactorPendingToken::class)
        ->and($event->getResponse())->not->toBeNull();
});

it('sets a JSON response containing challenge_id when 2FA is enforced', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $storage->shouldReceive('store')->once();

    [$registry] = makeTotpRegistry();
    $listener = new TwoFactorEnforcementListener($storage, $registry);
    $authenticator = Mockery::mock(AuthenticatorInterface::class);

    $user = makeTwoFactorUser('user-1', true);
    $event = makeSuccessEvent($authenticator, $user);

    $listener->onAuthenticationSuccess($event);

    $body = (string) $event->getResponse()->getBody();
    $data = json_decode($body, true);

    expect($data['message'])->toBe('two_factor_required')
        ->and($data['data'])->toHaveKey('challenge_id')
        ->and($data['data']['challenge_id'])->not->toBeEmpty();
});

it('does not enforce 2FA when user has 2FA disabled', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $storage->shouldNotReceive('store');

    [$registry] = makeTotpRegistry();
    $listener = new TwoFactorEnforcementListener($storage, $registry);
    $authenticator = Mockery::mock(AuthenticatorInterface::class);

    $user = makeTwoFactorUser('user-1', false);
    $event = makeSuccessEvent($authenticator, $user);

    $originalToken = $event->getToken();
    $listener->onAuthenticationSuccess($event);

    expect($event->getToken())->toBe($originalToken)
        ->and($event->getResponse())->toBeNull();
});

it('does not enforce 2FA when user does not implement TwoFactorAwareUserInterface', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $storage->shouldNotReceive('store');

    [$registry] = makeTotpRegistry();
    $listener = new TwoFactorEnforcementListener($storage, $registry);
    $authenticator = Mockery::mock(AuthenticatorInterface::class);

    $user = makeRegularUser('user-1');
    $event = makeSuccessEvent($authenticator, $user);

    $originalToken = $event->getToken();
    $listener->onAuthenticationSuccess($event);

    expect($event->getToken())->toBe($originalToken);
});

it('skips enforcement when authenticator is TwoFactorAuthenticator (avoids infinite loop)', function (): void {
    $storage = Mockery::mock(ChallengeStorageInterface::class);
    $storage->shouldNotReceive('store');

    [$registry] = makeTotpRegistry();
    $listener = new TwoFactorEnforcementListener($storage, $registry);

    $twoFactorAuthenticator = Mockery::mock(TwoFactorAuthenticator::class);

    $user = makeTwoFactorUser('user-1', true);
    $event = makeSuccessEvent($twoFactorAuthenticator, $user);

    $originalToken = $event->getToken();
    $listener->onAuthenticationSuccess($event);

    expect($event->getToken())->toBe($originalToken);
});
