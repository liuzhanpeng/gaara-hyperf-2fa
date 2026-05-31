# 自定义 2FA 方式

除内置的 TOTP、Email OTP、SMS OTP 外，可通过实现 `TwoFactorMethodInterface` 接入任意自定义验证方式。

## 步骤一：定义用户接口（可选）

若自定义方式需要从用户对象读取额外信息，建议定义专属用户接口：

```php
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;

interface PushUserInterface extends TwoFactorAwareUserInterface
{
    /**
     * 返回推送设备 Token.
     */
    public function getPushDeviceToken(): string;
}
```

## 步骤二：实现 TwoFactorMethodInterface

```php
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodInterface;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;
use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;

class PushNotificationMethod implements TwoFactorMethodInterface
{
    public function __construct(
        private PushService $pushService,
    ) {}

    public function type(): string
    {
        return 'push';   // 与 getPreferredTwoFactorMethod() 返回值对应
    }

    public function supports(TwoFactorAwareUserInterface $user): bool
    {
        return $user instanceof PushUserInterface;
    }

    public function initChallenge(TwoFactorAwareUserInterface $user): array
    {
        // 生成一次性令牌并推送给用户设备
        $token = bin2hex(random_bytes(16));

        /** @var PushUserInterface $user */
        $this->pushService->send($user->getPushDeviceToken(), $token);

        return [
            'metadata' => [
                // 存哈希，不存明文
                'token_hash' => hash('sha256', $token),
            ],
            'response' => [
                // 可附加客户端提示信息
                'message' => '已向您的手机发送推送通知，请在 App 中确认',
            ],
        ];
    }

    public function verify(TwoFactorAwareUserInterface $user, string $code, TwoFactorChallenge $challenge): bool
    {
        $stored = $challenge->metadata['token_hash'] ?? null;
        if (! is_string($stored)) {
            return false;
        }

        // 使用 hash_equals 防止时序攻击
        return hash_equals($stored, hash('sha256', $code));
    }
}
```

## 步骤三：扩展 Builder 以注册自定义方式

继承 `TwoFactorAuthenticatorBuilder`，重写 `buildMethod()`：

```php
use GaaraHyperf\TwoFactor\TwoFactorAuthenticatorBuilder;
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodInterface;

class MyTwoFactorAuthenticatorBuilder extends TwoFactorAuthenticatorBuilder
{
    protected function buildMethod(string $type, array $config): TwoFactorMethodInterface
    {
        if ($type === 'push') {
            return new PushNotificationMethod(
                $this->container->get(PushService::class),
            );
        }

        return parent::buildMethod($type, $config);
    }
}
```

## 步骤四：替换 Builder 注册

在应用的 `ServiceProvider` 或配置中，将自定义 Builder 注册给 `AuthenticatorFactory`：

```php
use GaaraHyperf\Authenticator\AuthenticatorFactory;

$container->get(AuthenticatorFactory::class)
    ->registerBuilder('two_factor', MyTwoFactorAuthenticatorBuilder::class);
```

## 步骤五：配置启用

```php
'two_factor' => [
    'methods' => [
        'push' => [],   // 自定义方式键名与 type() 返回值一致
    ],
],
```

## 安全建议

- `initChallenge()` 生成的随机令牌/码必须使用 `hash('sha256', $code)` 存入 `metadata`，不要存明文。
- `verify()` 中哈希比对必须使用 `hash_equals()` 防止时序攻击。
- 如需限制单次挑战的尝试次数，可在 `verify()` 中更新 `metadata` 计数，或使用 gaara-hyperf 的 RateLimiter 机制。
