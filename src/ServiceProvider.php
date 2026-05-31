<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\Authenticator\AuthenticatorFactory;
use GaaraHyperf\ServiceProvider\ServiceProviderInterface;
use Hyperf\Contract\ContainerInterface;

/**
 * 2FA 扩展服务提供者.
 *
 * 向 AuthenticatorFactory 注册 two_factor 认证器构建器。
 */
class ServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->get(AuthenticatorFactory::class)
            ->registerBuilder('two_factor', TwoFactorAuthenticatorBuilder::class);
    }
}
