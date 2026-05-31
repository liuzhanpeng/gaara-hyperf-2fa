<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;

it('is not expired when issued within the ttl window', function (): void {
    $challenge = new TwoFactorChallenge('user-1', 'api', time());
    expect($challenge->isExpired(300))->toBeFalse();
});

it('is expired when issued before the ttl window', function (): void {
    $challenge = new TwoFactorChallenge('user-1', 'api', time() - 301);
    expect($challenge->isExpired(300))->toBeTrue();
});

it('exposes user identifier and guard name', function (): void {
    $challenge = new TwoFactorChallenge('user-42', 'web', 1000);
    expect($challenge->userIdentifier)->toBe('user-42')
        ->and($challenge->guardName)->toBe('web')
        ->and($challenge->issuedAt)->toBe(1000);
});
