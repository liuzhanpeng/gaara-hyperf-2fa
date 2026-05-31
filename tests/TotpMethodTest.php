<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\Method\TotpMethod;
use GaaraHyperf\TwoFactor\Totp\TotpVerifierInterface;
use GaaraHyperf\TwoFactor\TotpUserInterface;

it('returns type totp', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $method = new TotpMethod($verifier);

    expect($method->type())->toBe('totp');
});

it('supports TotpUserInterface users', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $method = new TotpMethod($verifier);

    $user = makeTwoFactorUser('user-1', true, 'SECRET');
    expect($method->supports($user))->toBeTrue();
});

it('does not support users that are not TotpUserInterface', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $method = new TotpMethod($verifier);

    // An email-OTP user implements TwoFactorAwareUserInterface but NOT TotpUserInterface
    $user = makeEmailOtpUser('user-1', true, 'user@example.com');
    expect($method->supports($user))->toBeFalse();
});

it('initChallenge returns empty metadata and response', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $method = new TotpMethod($verifier);

    $user = makeTwoFactorUser('user-1', true, 'SECRET');
    $result = $method->initChallenge($user);

    expect($result['metadata'])->toBe([])
        ->and($result['response'])->toBe([]);
});

it('verify returns true when code is correct', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $verifier->shouldReceive('verify')->with('SECRET', '123456')->andReturn(true);

    $method = new TotpMethod($verifier);
    $user = makeTwoFactorUser('user-1', true, 'SECRET');
    $challenge = new TwoFactorChallenge('user-1', 'api', 'totp', time());

    expect($method->verify($user, '123456', $challenge))->toBeTrue();
});

it('verify returns false when code is wrong', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $verifier->shouldReceive('verify')->with('SECRET', 'wrong')->andReturn(false);

    $method = new TotpMethod($verifier);
    $user = makeTwoFactorUser('user-1', true, 'SECRET');
    $challenge = new TwoFactorChallenge('user-1', 'api', 'totp', time());

    expect($method->verify($user, 'wrong', $challenge))->toBeFalse();
});

it('verify returns false when user does not implement TotpUserInterface', function (): void {
    $verifier = Mockery::mock(TotpVerifierInterface::class);
    $verifier->shouldNotReceive('verify');

    $method = new TotpMethod($verifier);
    // EmailOtpUser implements TwoFactorAwareUserInterface but not TotpUserInterface
    $user = makeEmailOtpUser('user-1', true, 'user@example.com');
    $challenge = new TwoFactorChallenge('user-1', 'api', 'totp', time());

    expect($method->verify($user, '123456', $challenge))->toBeFalse();
});
