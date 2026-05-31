# 配置参考

## 完整配置示例

```php
// config/autoload/gaara.php

'guards' => [
    'api' => [
        'user_provider' => [
            'driver' => 'eloquent',
            'model'  => App\Model\User::class,
        ],
        'authenticators' => [
            // 第一步认证（账号密码）
            'json_login' => [
                'login_path'     => '/login',
                'username_field' => 'email',
                'password_field' => 'password',
            ],

            // 第二步认证（2FA 验证）
            'two_factor' => [
                'verify_path'        => '/two-factor/verify',  // 验证端点（POST）
                'challenge_ttl'      => 300,                   // 挑战有效期（秒）
                'code_field'         => 'code',                // 请求体验证码字段
                'challenge_id_field' => 'challenge_id',        // 请求体挑战 ID 字段
                'storage'            => ['type' => 'session'], // 挑战存储驱动
                'methods' => [
                    'totp' => ['leeway' => 30],                // 启用 TOTP，时间容差 30s
                ],
            ],
        ],
    ],
],
```

---

## methods 配置

`methods` 是一个关联数组，键为方式类型标识，值为该方式的选项。可同时启用多种方式，用户通过 `getPreferredTwoFactorMethod()` 返回值决定使用哪种。

### totp

```php
'totp' => [
    'leeway' => 30,   // 时间容差（秒），默认 30。允许时间偏差在此范围内的码通过验证
],
```

用户模型需实现 `TotpUserInterface`，`getTwoFactorSecret()` 返回 Base32 编码的密钥。

生成密钥示例：

```php
use OTPHP\TOTP;

$totp = TOTP::generate();
$secret = $totp->getSecret(); // 存入数据库
$qrUri  = $totp->getQrCodeUri('https://your-app.com', 'user@example.com');
```

### email

```php
'email' => [
    'code_length' => 6,   // 验证码位数，默认 6
],
```

需在容器中绑定 `EmailCodeSenderInterface`：

```php
// config/autoload/dependencies.php
return [
    GaaraHyperf\TwoFactor\Method\EmailCodeSenderInterface::class => App\TwoFactor\MailSender::class,
];
```

```php
// app/TwoFactor/MailSender.php
use GaaraHyperf\TwoFactor\Method\EmailCodeSenderInterface;
use GaaraHyperf\TwoFactor\EmailOtpUserInterface;

class MailSender implements EmailCodeSenderInterface
{
    public function send(EmailOtpUserInterface $user, string $code): void
    {
        // 使用 Hyperf Mailer 等发送邮件
        mail($user->getTwoFactorEmail(), '验证码', "您的验证码：{$code}，5分钟内有效。");
    }
}
```

### sms

```php
'sms' => [
    'code_length' => 6,   // 验证码位数，默认 6
],
```

需在容器中绑定 `SmsCodeSenderInterface`：

```php
// config/autoload/dependencies.php
return [
    GaaraHyperf\TwoFactor\Method\SmsCodeSenderInterface::class => App\TwoFactor\AliyunSmsSender::class,
];
```

```php
// app/TwoFactor/AliyunSmsSender.php
use GaaraHyperf\TwoFactor\Method\SmsCodeSenderInterface;
use GaaraHyperf\TwoFactor\SmsOtpUserInterface;

class AliyunSmsSender implements SmsCodeSenderInterface
{
    public function send(SmsOtpUserInterface $user, string $code): void
    {
        // 调用阿里云 / 腾讯云等短信 SDK 发送
    }
}
```

### 多方式同时启用

```php
'methods' => [
    'totp'  => ['leeway' => 30],
    'email' => ['code_length' => 6],
    'sms'   => ['code_length' => 6],
],
```

用户的 `getPreferredTwoFactorMethod()` 返回值决定实际使用哪种。若返回的方式未在 `methods` 中注册，会抛出 `InvalidArgumentException`。

---

## storage 配置

### session（默认）

```php
'storage' => ['type' => 'session'],
```

依赖 `Hyperf\Contract\SessionInterface`，适合 Web 应用。需确保 `hyperf/session` 已安装并配置中间件。

### redis

```php
'storage' => [
    'type' => 'redis',
    'ttl'  => 300,   // 可选，默认与 challenge_ttl 相同
],
```

依赖 `hyperf/redis`：

```bash
composer require hyperf/redis
```

Redis 键格式：`gaara:2fa:challenge:{challengeId}`，TTL 由 `setex` 自动管理。适合 API / 无状态场景。

---

## 自定义成功/失败处理器

与其他认证器相同，支持注入自定义处理器：

```php
'two_factor' => [
    // ...
    'success_handler' => App\TwoFactor\TwoFactorSuccessHandler::class,
    'failure_handler' => App\TwoFactor\TwoFactorFailureHandler::class,
],
```
