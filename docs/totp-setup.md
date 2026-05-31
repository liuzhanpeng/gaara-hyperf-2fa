# TOTP 密钥管理

本文介绍如何在应用中管理用户的 TOTP 密钥，包括密钥生成、二维码展示和禁用流程。

## 安装 otphp

`gaara-hyperf-2fa` 已依赖 `spomky-labs/otphp`，无需额外安装。

## 生成密钥

```php
use OTPHP\TOTP;

// 生成一个新的 TOTP 实例（自动生成随机密钥）
$totp = TOTP::generate();

// Base32 密钥，存入数据库
$secret = $totp->getSecret();

// 生成二维码 URI（用于展示给用户扫描）
$issuer = 'YourApp';
$label  = $user->getEmail();          // 账号标识，显示在 Authenticator App 中
$uri    = $totp->getProvisioningUri(); // otpauth://totp/...

// 也可以手动设置 issuer 和 label
$totp->setIssuer($issuer);
$totp->setLabel($label);
$uri = $totp->getProvisioningUri();
```

## 展示二维码

将 `$uri` 传给前端，使用任意二维码库渲染：

```php
// 返回 JSON 给前端
return response()->json([
    'secret'  => $secret,   // 可选：同时展示文字密钥供手动输入
    'qr_uri'  => $uri,
]);
```

前端使用 `qrcode.js`（或其他库）渲染：

```js
QRCode.toCanvas(canvas, qrUri);
```

用户扫码后，在 Google Authenticator / Authy / 1Password 等 App 中会看到对应账号的动态码。

## 开启 2FA 流程

建议完整流程：

1. 生成密钥，展示二维码和文字密钥。
2. 要求用户输入当前动态码验证一次（确认 App 配置成功）。
3. 验证通过后，将密钥存入数据库，`two_factor_enabled = true`。

```php
use OTPHP\TOTP;

// 验证用户提交的码（开启 2FA 时的确认步骤）
public function enableTwoFactor(Request $request): Response
{
    $secret = $request->session()->get('pending_totp_secret');
    $code   = $request->input('code');

    $totp = TOTP::createFromSecret($secret);
    if (! $totp->verify($code, null, 30)) {  // leeway=30s
        return response()->json(['error' => '验证码错误'], 422);
    }

    // 保存密钥并开启 2FA
    $user->totp_secret       = $secret;
    $user->two_factor_enabled = true;
    $user->save();

    return response()->json(['message' => '2FA 已开启']);
}
```

## 禁用 2FA

```php
$user->two_factor_enabled = false;
$user->totp_secret        = null;
$user->save();
```

禁用时建议要求用户再次输入当前密码或动态码进行身份确认。

## 用户模型示例

```php
use GaaraHyperf\TwoFactor\TotpUserInterface;

class User extends Model implements TotpUserInterface
{
    protected $casts = [
        'two_factor_enabled' => 'boolean',
    ];

    public function getIdentifier(): string
    {
        return (string) $this->id;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled === true
            && ! empty($this->totp_secret);
    }

    public function getPreferredTwoFactorMethod(): string
    {
        return 'totp';
    }

    public function getTwoFactorSecret(): string
    {
        return $this->totp_secret;
    }
}
```
