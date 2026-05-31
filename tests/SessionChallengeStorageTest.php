<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\ChallengeStorage\SessionChallengeStorage;
use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use Hyperf\Contract\SessionInterface;

it('stores, retrieves, and deletes a challenge via session', function (): void {
    $session = Mockery::mock(SessionInterface::class);
    $storage = new SessionChallengeStorage($session);

    $challenge = new TwoFactorChallenge('user-1', 'api', time());
    $key = '_gaara_2fa_challenge_abc123';
    $encoded = json_encode([
        'user_identifier' => 'user-1',
        'guard_name' => 'api',
        'issued_at' => $challenge->issuedAt,
    ]);

    $session->shouldReceive('set')->once()->with($key, Mockery::type('string'));
    $session->shouldReceive('get')->once()->with($key)->andReturn($encoded);
    $session->shouldReceive('remove')->once()->with($key);

    $storage->store('abc123', $challenge);

    $retrieved = $storage->get('abc123');
    expect($retrieved)->toBeInstanceOf(TwoFactorChallenge::class)
        ->and($retrieved->userIdentifier)->toBe('user-1');

    $storage->delete('abc123');
});

it('returns null when session key is missing', function (): void {
    $session = Mockery::mock(SessionInterface::class);
    $session->shouldReceive('get')->once()->andReturn(null);

    $storage = new SessionChallengeStorage($session);
    expect($storage->get('nonexistent'))->toBeNull();
});

it('returns null when session value is corrupted', function (): void {
    $session = Mockery::mock(SessionInterface::class);
    $session->shouldReceive('get')->once()->andReturn('not-valid-serialized-data');

    $storage = new SessionChallengeStorage($session);
    expect($storage->get('bad'))->toBeNull();
});
