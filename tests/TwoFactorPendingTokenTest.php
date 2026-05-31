<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\TwoFactorPendingToken;

it('returns guard name and user identifier', function (): void {
    $token = new TwoFactorPendingToken('web', 'user-1', 'abc123');

    expect($token->getGuardName())->toBe('web')
        ->and($token->getUserIdentifier())->toBe('user-1')
        ->and($token->getChallengeId())->toBe('abc123');
});

it('has no attributes', function (): void {
    $token = new TwoFactorPendingToken('web', 'user-1', 'abc123');

    expect($token->hasAttribute('foo'))->toBeFalse()
        ->and($token->getAttribute('foo'))->toBeNull();
});

it('serializes and deserializes correctly', function (): void {
    $token = new TwoFactorPendingToken('api', 'user-99', 'challenge-xyz');

    $data = serialize($token);
    /** @var TwoFactorPendingToken $restored */
    $restored = unserialize($data, ['allowed_classes' => [TwoFactorPendingToken::class]]);

    expect($restored->getGuardName())->toBe('api')
        ->and($restored->getUserIdentifier())->toBe('user-99')
        ->and($restored->getChallengeId())->toBe('challenge-xyz');
});
