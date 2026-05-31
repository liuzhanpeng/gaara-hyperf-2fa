<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\Authenticator\AuthenticatorInterface;
use GaaraHyperf\Authenticator\Builder\AbstractAuthenticatorBuilder;
use GaaraHyperf\TwoFactor\ChallengeStorage\ChallengeStorageFactory;
use GaaraHyperf\TwoFactor\EventListener\TwoFactorEnforcementListener;
use GaaraHyperf\TwoFactor\Method\EmailCodeSenderInterface;
use GaaraHyperf\TwoFactor\Method\EmailOtpMethod;
use GaaraHyperf\TwoFactor\Method\SmsCodeSenderInterface;
use GaaraHyperf\TwoFactor\Method\SmsOtpMethod;
use GaaraHyperf\TwoFactor\Method\TotpMethod;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodInterface;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodRegistry;
use GaaraHyperf\TwoFactor\Totp\OtphpTotpVerifier;
use GaaraHyperf\TwoFactor\Totp\TotpVerifierInterface;
use GaaraHyperf\UserProvider\UserProviderInterface;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 双因素认证器构建器.
 *
 * 在 auth.php 的 authenticators 中配置 two_factor 即可启用：
 *
 * ```php
 * 'authenticators' => [
 *     // ... 其他认证器 ...
 *     'two_factor' => [
 *         'verify_path'        => '/two-factor/verify',
 *         'challenge_ttl'      => 300,
 *         'code_field'         => 'code',
 *         'challenge_id_field' => 'challenge_id',
 *         'storage'            => ['type' => 'session'],
 *         'methods' => [
 *             'totp'  => ['leeway' => 30],
 *             'email' => ['code_length' => 6],
 *         ],
 *     ],
 * ],
 * ```
 *
 * 构建时会同时向 EventDispatcher 注册 TwoFactorEnforcementListener，
 * 使其能拦截同一 Guard 中其他认证器的成功事件并强制二次验证。
 */
class TwoFactorAuthenticatorBuilder extends AbstractAuthenticatorBuilder
{
    public function create(array $options, UserProviderInterface $userProvider, EventDispatcher $eventDispatcher): AuthenticatorInterface
    {
        $options = $options + [
            'verify_path' => '/two-factor/verify',
            'challenge_ttl' => 300,
            'code_field' => 'code',
            'challenge_id_field' => 'challenge_id',
            'storage' => ['type' => 'session'],
            'methods' => ['totp' => []],
        ];

        $challengeStorage = $this->container
            ->get(ChallengeStorageFactory::class)
            ->create($options['storage'], (int) $options['challenge_ttl']);

        $registry = new TwoFactorMethodRegistry();
        foreach ($options['methods'] as $type => $methodConfig) {
            $registry->register($this->buildMethod($type, (array) $methodConfig));
        }

        // 注册强制执行监听器，使其监听同一 Guard 的所有认证成功事件
        $eventDispatcher->addSubscriber(new TwoFactorEnforcementListener($challengeStorage, $registry));

        return new TwoFactorAuthenticator(
            verifyPath: $options['verify_path'],
            challengeStorage: $challengeStorage,
            methodRegistry: $registry,
            userProvider: $userProvider,
            challengeTtl: (int) $options['challenge_ttl'],
            codeField: $options['code_field'],
            challengeIdField: $options['challenge_id_field'],
            successHandler: $this->createSuccessHandler($options),
            failureHandler: $this->createFailureHandler($options),
        );
    }

    private function buildMethod(string $type, array $config): TwoFactorMethodInterface
    {
        return match ($type) {
            'totp' => new TotpMethod(
                $this->container->has(TotpVerifierInterface::class)
                    ? $this->container->get(TotpVerifierInterface::class)
                    : new OtphpTotpVerifier((int) ($config['leeway'] ?? 30))
            ),
            'email' => new EmailOtpMethod(
                $this->container->get(EmailCodeSenderInterface::class),
                (int) ($config['code_length'] ?? 6),
            ),
            'sms' => new SmsOtpMethod(
                $this->container->get(SmsCodeSenderInterface::class),
                (int) ($config['code_length'] ?? 6),
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unknown 2FA method type: "%s". Built-in types: totp, email, sms.', $type)
            ),
        };
    }
}
