<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\Authenticator\AuthenticatorInterface;
use GaaraHyperf\Authenticator\Builder\AbstractAuthenticatorBuilder;
use GaaraHyperf\TwoFactor\ChallengeStorage\ChallengeStorageFactory;
use GaaraHyperf\TwoFactor\EventListener\TwoFactorEnforcementListener;
use GaaraHyperf\TwoFactor\Totp\OtphpTotpVerifier;
use GaaraHyperf\TwoFactor\Totp\TotpVerifierInterface;
use GaaraHyperf\UserProvider\UserProviderInterface;
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
 *         'verify_path'        => '/two-factor/verify',  // TOTP 验证端点
 *         'challenge_ttl'      => 300,                   // 挑战有效期（秒）
 *         'code_field'         => 'code',                // 表单字段名
 *         'challenge_id_field' => 'challenge_id',        // 挑战 ID 字段名
 *         'storage'            => ['type' => 'session'], // 存储驱动配置
 *         // 'success_handler' => ...,
 *         // 'failure_handler' => ...,
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
        ];

        $challengeStorage = $this->container
            ->get(ChallengeStorageFactory::class)
            ->create($options['storage'], (int) $options['challenge_ttl']);

        $totpVerifier = $this->container->has(TotpVerifierInterface::class)
            ? $this->container->get(TotpVerifierInterface::class)
            : new OtphpTotpVerifier();

        // 注册强制执行监听器，使其监听同一 Guard 的所有认证成功事件
        $eventDispatcher->addSubscriber(new TwoFactorEnforcementListener($challengeStorage));

        return new TwoFactorAuthenticator(
            verifyPath: $options['verify_path'],
            challengeStorage: $challengeStorage,
            totpVerifier: $totpVerifier,
            userProvider: $userProvider,
            challengeTtl: (int) $options['challenge_ttl'],
            codeField: $options['code_field'],
            challengeIdField: $options['challenge_id_field'],
            successHandler: $this->createSuccessHandler($options),
            failureHandler: $this->createFailureHandler($options),
        );
    }
}
