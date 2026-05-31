# 架构说明

## 整体设计

`gaara-hyperf-2fa` 作为 `gaara-hyperf` 的扩展，完全复用其认证事件管道，通过以下两个切入点介入认证流程，不修改任何核心代码：

1. **`TwoFactorEnforcementListener`**：监听 `AuthenticationSuccessEvent`，决定是否强制 2FA。
2. **`TwoFactorAuthenticator`**：处理验证端点请求，完成码校验并颁发最终令牌。

## 组件关系图

```
Guard
 ├── JsonLoginAuthenticator (或其他认证器)
 │    └── [success] AuthenticationSuccessEvent
 │              └── TwoFactorEnforcementListener
 │                    ├── [2FA disabled]  → 放行，不修改令牌
 │                    └── [2FA enabled]   → initChallenge()
 │                                            存储 TwoFactorChallenge
 │                                            替换为 TwoFactorPendingToken
 │                                            返回 two_factor_required 响应
 │
 └── TwoFactorAuthenticator (POST /two-factor/verify)
       └── [valid code] → 删除挑战，颁发 AuthenticatedToken
```

## 核心类说明

### 接口与用户契约

| 接口 | 职责 |
|------|------|
| `TwoFactorAwareUserInterface` | 所有 2FA 用户的基础接口：`isTwoFactorEnabled()` + `getPreferredTwoFactorMethod()` |
| `TotpUserInterface` | 扩展基础接口，增加 `getTwoFactorSecret()` |
| `EmailOtpUserInterface` | 扩展基础接口，增加 `getTwoFactorEmail()` |
| `SmsOtpUserInterface` | 扩展基础接口，增加 `getTwoFactorPhoneNumber()` |

### 方式层（Method Layer）

| 类 / 接口 | 职责 |
|-----------|------|
| `TwoFactorMethodInterface` | 定义方式契约：`type()` / `supports()` / `initChallenge()` / `verify()` |
| `TwoFactorMethodRegistry` | 类型 → 方式实例的注册表，供 Listener 和 Authenticator 按类型查找 |
| `TotpMethod` | 封装 `TotpVerifierInterface`，`initChallenge` 为空，`verify` 比对 TOTP 码 |
| `EmailOtpMethod` | 调用 `EmailCodeSenderInterface` 发码，哈希存 metadata |
| `SmsOtpMethod` | 调用 `SmsCodeSenderInterface` 发码，哈希存 metadata |

### 挑战存储（Challenge Storage）

| 类 | 职责 |
|----|------|
| `TwoFactorChallenge` | 不可变值对象：`userIdentifier` / `guardName` / `method` / `issuedAt` / `metadata` |
| `ChallengeStorageInterface` | `store` / `get` / `delete` |
| `SessionChallengeStorage` | 基于 `Hyperf\Contract\SessionInterface` |
| `RedisChallengeStorage` | 基于 `Hyperf\Redis\Redis`，TTL 由 Redis `setex` 保证 |
| `ChallengeStorageFactory` | 根据 `['type' => 'session'|'redis']` 实例化对应驱动 |

### 令牌

| 类 | 职责 |
|----|------|
| `TwoFactorPendingToken` | 实现 `TokenInterface` 但**不是** `AuthenticatedToken`，使 `Guard::isTokenAuthenticated()` 返回 false |

## 数据流：initChallenge 返回格式

`TwoFactorMethodInterface::initChallenge()` 返回：

```php
[
    'metadata' => [/* 存入 TwoFactorChallenge，verify() 时读取 */],
    'response' => [/* 合并进 two_factor_required 响应的 data 字段 */],
]
```

- TOTP：`metadata=[]`，`response=[]`（客户端已有 Authenticator App）
- Email OTP：`metadata=['code_hash'=>sha256(code)]`，`response=['hint'=>'u**r@...']`
- SMS OTP：`metadata=['code_hash'=>sha256(code)]`，`response=['hint'=>'138****5678']`

## 注册流程

```
Hyperf 启动
 └── ConfigProvider → 注册 InitListener
      └── ServiceProviderRegisterEvent
           └── InitListener::process()
                └── ServiceProvider::register()
                     └── AuthenticatorFactory::registerBuilder('two_factor', TwoFactorAuthenticatorBuilder::class)

Guard 构建时
 └── TwoFactorAuthenticatorBuilder::create()
      ├── ChallengeStorageFactory::create()  → 挑战存储实例
      ├── TwoFactorMethodRegistry            ← 注册各方式
      ├── EventDispatcher::addSubscriber(TwoFactorEnforcementListener)
      └── new TwoFactorAuthenticator(...)
```
