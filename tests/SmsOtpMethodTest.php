<?php

declare(strict_types=1);

use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\Method\SmsCodeSenderInterface;
use GaaraHyperf\TwoFactor\Method\SmsOtpMethod;

it('returns type sms', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $method = new SmsOtpMethod($sender);

    expect($method->type())->toBe('sms');
});

it('supports SmsOtpUserInterface users', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $method = new SmsOtpMethod($sender);

    $user = makeSmsOtpUser();
    expect($method->supports($user))->toBeTrue();
});

it('does not support users that are not SmsOtpUserInterface', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $method = new SmsOtpMethod($sender);

    $user = makeEmailOtpUser();
    expect($method->supports($user))->toBeFalse();
});

it('initChallenge sends SMS and returns code_hash in metadata and hint in response', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $sender->shouldReceive('send')->once();

    $method = new SmsOtpMethod($sender);
    $user = makeSmsOtpUser('user-1', true, '13812345678');

    $result = $method->initChallenge($user);

    expect($result)->toHaveKey('metadata')
        ->and($result['metadata'])->toHaveKey('code_hash')
        ->and($result['response'])->toHaveKey('hint')
        ->and($result['response']['hint'])->toContain('*');
});

it('masks phone number in hint', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $sender->shouldReceive('send')->once();

    $method = new SmsOtpMethod($sender);
    $user = makeSmsOtpUser('user-1', true, '13812345678');

    $result = $method->initChallenge($user);

    expect($result['response']['hint'])->toBe('138****5678');
});

it('verify returns true when code matches stored hash', function (): void {
    $code = '123456';
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $method = new SmsOtpMethod($sender);

    $user = makeSmsOtpUser();
    $challenge = new TwoFactorChallenge('user-1', 'api', 'sms', time(), [
        'code_hash' => hash('sha256', $code),
    ]);

    expect($method->verify($user, $code, $challenge))->toBeTrue();
});

it('verify returns false when code does not match', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $method = new SmsOtpMethod($sender);

    $user = makeSmsOtpUser();
    $challenge = new TwoFactorChallenge('user-1', 'api', 'sms', time(), [
        'code_hash' => hash('sha256', '123456'),
    ]);

    expect($method->verify($user, 'wrong', $challenge))->toBeFalse();
});

it('verify returns false when metadata is missing code_hash', function (): void {
    $sender = Mockery::mock(SmsCodeSenderInterface::class);
    $method = new SmsOtpMethod($sender);

    $user = makeSmsOtpUser();
    $challenge = new TwoFactorChallenge('user-1', 'api', 'sms', time(), []);

    expect($method->verify($user, '123456', $challenge))->toBeFalse();
});
