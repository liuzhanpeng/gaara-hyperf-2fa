# gaara-hyperf-2fa

[English](README.en.md)

[gaara-hyperf](https://github.com/liuzhanpeng/gaara-hyperf) 的双因素认证（2FA）扩展，支持 TOTP、Email OTP、SMS OTP，并可通过实现接口自由扩展更多方式。

## 特性

- [x] TOTP（基于时间的一次性密码，兼容 Google Authenticator / Authy 等）
- [x] Email OTP（发送随机验证码到邮箱）
- [x] SMS OTP（发送随机验证码到手机）
- [x] 可扩展的多方式架构（实现 `TwoFactorMethodInterface` 即可接入自定义方式）
- [x] 挑战存储：Session（默认）/ Redis（可选）
- [x] 零侵入：现有 Guard 配置无需改动，按用户状态按需启用
- [x] 安全：挑战 TTL 自动过期，码一次性使用，哈希存储（不留明文）

---

## 安装

```bash
composer require lzpeng/gaara-hyperf-2fa
```

> **前置依赖**：`lzpeng/gaara-hyperf`（已安装并完成配置）

---

## 快速开始

### 1. 用户模型实现接口

根据所需的 2FA 方式，让用户模型实现对应接口：

**TOTP**

```php
use GaaraHyperf\TwoFactor\TotpUserInterface;

class User implements TotpUserInterface
{
    public function getIdentifier(): string { return $this->id; }
    public function isTwoFactorEnabled(): bool { return $this->two_factor_enabled; }
    public function getPreferredTwoFactorMethod(): string { return 'totp'; }
    public function getTwoFactorSecret(): string { return $this->totp_secret; } // Base32 编码
}
```

**Email OTP**

```php
use GaaraHyperf\TwoFactor\EmailOtpUserInterface;

class User implements EmailOtpUserInterface
{
    public function isTwoFactorEnabled(): bool { return $this->two_factor_enabled; }
    public function getPreferredTwoFactorMethod(): string { return 'email'; }
    public function getTwoFactorEmail(): string { return $this->email; }
}
```

**SMS OTP**

```php
use GaaraHyperf\TwoFactor\SmsOtpUserInterface;

class User implements SmsOtpUserInterface
{
    public function isTwoFactorEnabled(): bool { return $this->two_factor_enabled; }
    public function getPreferredTwoFactorMethod(): string { return 'sms'; }
    public function getTwoFactorPhoneNumber(): string { return $this->phone; }
}
```

### 2. 在 Guard 中注册 two_factor 认证器

编辑 `config/autoload/gaara.php`：

```php
'guards' => [
    'api' => [
        'user_provider' => [...],
        'authenticators' => [
            'json_login' => [
                'login_path' => '/login',
                'username_field' => 'email',
                'password_field' => 'password',
            ],
            'two_factor' => [                          // 新增此节点
                'verify_path'        => '/two-factor/verify',
                'challenge_ttl'      => 300,           // 挑战有效期（秒）
                'code_field'         => 'code',
                'challenge_id_field' => 'challenge_id',
                'storage'            => ['type' => 'session'],
                'methods' => [
                    'totp' => ['leeway' => 30],        // 时间容差（秒）
                ],
            ],
        ],
    ],
],
```

### 3. 绑定发送器（Email / SMS）

若使用 Email 或 SMS 方式，需在容器中绑定发送器实现：

```php
// config/autoload/dependencies.php
use GaaraHyperf\TwoFactor\Method\EmailCodeSenderInterface;
use GaaraHyperf\TwoFactor\Method\SmsCodeSenderInterface;
use App\TwoFactor\HyperfMailer2FASender;
use App\TwoFactor\AliyunSms2FASender;

return [
    EmailCodeSenderInterface::class => HyperfMailer2FASender::class,
    SmsCodeSenderInterface::class   => AliyunSms2FASender::class,
];
```

---

## 认证流程

```
POST /login
 └─ JsonLoginAuthenticator 验证账号密码
     └─ [成功] → AuthenticationSuccessEvent
         └─ TwoFactorEnforcementListener 检查用户是否启用 2FA
             ├─ 未启用 → 正常颁发令牌（不影响原流程）
             └─ 已启用 → 初始化挑战（发送 TOTP/Email/SMS）
                          替换为 TwoFactorPendingToken
                          返回 HTTP 200 {code:1, message:"two_factor_required", data:{challenge_id, ...hint}}

POST /two-factor/verify  {challenge_id, code}
 └─ TwoFactorAuthenticator 验证码
     ├─ 无效/过期 → 401 认证失败
     └─ 有效 → 删除挑战，颁发最终 AuthenticatedToken
```

### 登录响应示例

**需要 2FA（Email 方式）：**

```json
{
  "code": 1,
  "message": "two_factor_required",
  "data": {
    "challenge_id": "550e8400-e29b-41d4-a716-446655440000",
    "hint": "u**r@e*****e.com"
  }
}
```

**需要 2FA（SMS 方式）：**

```json
{
  "code": 1,
  "message": "two_factor_required",
  "data": {
    "challenge_id": "550e8400-e29b-41d4-a716-446655440000",
    "hint": "138****5678"
  }
}
```

**TOTP 无附加 hint（客户端自行读取 Authenticator App）：**

```json
{
  "code": 1,
  "message": "two_factor_required",
  "data": {
    "challenge_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### 验证请求示例

```json
POST /two-factor/verify
{
  "challenge_id": "550e8400-e29b-41d4-a716-446655440000",
  "code": "123456"
}
```

---

## 配置参考

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `verify_path` | string | `/two-factor/verify` | 验证端点路径（POST） |
| `challenge_ttl` | int | `300` | 挑战有效期（秒） |
| `code_field` | string | `code` | 请求体中验证码字段名 |
| `challenge_id_field` | string | `challenge_id` | 请求体中挑战 ID 字段名 |
| `storage.type` | string | `session` | 挑战存储驱动：`session` / `redis` |
| `storage.ttl` | int | 同 `challenge_ttl` | Redis 驱动 key 过期时间（秒） |
| `methods` | array | `['totp' => []]` | 启用的方式及其配置 |
| `methods.totp.leeway` | int | `30` | TOTP 时间容差（秒） |
| `methods.email.code_length` | int | `6` | Email OTP 码长度 |
| `methods.sms.code_length` | int | `6` | SMS OTP 码长度 |

### Redis 存储配置示例

```php
'storage' => [
    'type' => 'redis',
    'ttl'  => 300,
],
```

> 需安装 `hyperf/redis`：`composer require hyperf/redis`

---

## 扩展自定义方式

实现 `TwoFactorMethodInterface` 并注册到方法注册表即可：

```php
use GaaraHyperf\TwoFactor\Method\TwoFactorMethodInterface;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;
use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;

class PushNotificationMethod implements TwoFactorMethodInterface
{
    public function type(): string { return 'push'; }

    public function supports(TwoFactorAwareUserInterface $user): bool
    {
        return $user instanceof PushUserInterface;
    }

    public function initChallenge(TwoFactorAwareUserInterface $user): array
    {
        // 发送推送通知，生成随机令牌
        $token = bin2hex(random_bytes(16));
        $this->pushService->send($user, $token);
        return [
            'metadata' => ['token_hash' => hash('sha256', $token)],
            'response' => [],
        ];
    }

    public function verify(TwoFactorAwareUserInterface $user, string $code, TwoFactorChallenge $challenge): bool
    {
        $stored = $challenge->metadata['token_hash'] ?? null;
        return is_string($stored) && hash_equals($stored, hash('sha256', $code));
    }
}
```

自定义方式目前需通过扩展 `TwoFactorAuthenticatorBuilder` 的 `buildMethod()` 或直接在应用层注册到 `TwoFactorMethodRegistry` 来接入。

---

## 用户接口速查

| 接口 | 方法 | 适用方式 |
|------|------|---------|
| `TwoFactorAwareUserInterface` | `isTwoFactorEnabled()` `getPreferredTwoFactorMethod()` | 所有方式（基础接口） |
| `TotpUserInterface` | + `getTwoFactorSecret(): string` | TOTP |
| `EmailOtpUserInterface` | + `getTwoFactorEmail(): string` | Email OTP |
| `SmsOtpUserInterface` | + `getTwoFactorPhoneNumber(): string` | SMS OTP |

---

## 安全说明

- **挑战一次性使用**：验证通过后立即删除，防止重放攻击。
- **码哈希存储**：Email/SMS 明文码不写入存储，仅存 SHA-256 哈希。
- **挑战 TTL**：Session/Redis 存储均设置过期时间，超时挑战自动失效。
- **循环防护**：`TwoFactorEnforcementListener` 自动跳过 `TwoFactorAuthenticator` 触发的成功事件，避免死循环。
- **兼容性**：`isTwoFactorEnabled()` 返回 `false` 的用户完全不受影响，原有认证流程不变。

---

## 许可

MIT

