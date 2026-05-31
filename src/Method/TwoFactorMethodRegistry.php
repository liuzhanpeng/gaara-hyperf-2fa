<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\Method;

use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;
use InvalidArgumentException;

/**
 * 2FA 方式注册表.
 *
 * 维护已注册的所有 TwoFactorMethodInterface 实例，
 * 供 TwoFactorAuthenticator 和 TwoFactorEnforcementListener 按类型查找对应方式。
 */
class TwoFactorMethodRegistry
{
    /** @var array<string, TwoFactorMethodInterface> */
    private array $methods = [];

    /**
     * 注册一个 2FA 方式.
     */
    public function register(TwoFactorMethodInterface $method): void
    {
        $this->methods[$method->type()] = $method;
    }

    /**
     * 按类型标识解析方式.
     *
     * @throws InvalidArgumentException 类型未注册时抛出
     */
    public function resolve(string $type): TwoFactorMethodInterface
    {
        if (! isset($this->methods[$type])) {
            throw new InvalidArgumentException(
                sprintf('Unsupported 2FA method: "%s". Registered methods: %s.', $type, implode(', ', array_keys($this->methods)) ?: 'none')
            );
        }

        return $this->methods[$type];
    }

    /**
     * 根据用户偏好方式自动解析.
     *
     * @throws InvalidArgumentException 用户偏好的方式未注册时抛出
     */
    public function resolveForUser(TwoFactorAwareUserInterface $user): TwoFactorMethodInterface
    {
        return $this->resolve($user->getPreferredTwoFactorMethod());
    }

    /**
     * 判断指定类型是否已注册.
     */
    public function has(string $type): bool
    {
        return isset($this->methods[$type]);
    }
}
