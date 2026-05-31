# gaara-hyperf-2fa

Two-Factor Authentication (2FA) extension for [gaara-hyperf](https://github.com/liuzhanpeng/gaara-hyperf). Supports TOTP, Email OTP, and SMS OTP out of the box, and allows custom methods via a clean interface.

> 中文文档请查看 [README.md](README.md)

## Features

- [x] TOTP (time-based one-time passwords — compatible with Google Authenticator, Authy, etc.)
- [x] Email OTP (random code sent to the user's email)
- [x] SMS OTP (random code sent by SMS)
- [x] Extensible multi-method architecture (implement `TwoFactorMethodInterface` to add custom methods)
- [x] Challenge storage: Session (default) / Redis (optional)
- [x] Zero-invasion: existing Guard config needs no changes; 2FA is applied per-user, on demand
- [x] Secure: challenge TTL, single-use codes, SHA-256 hashed storage (no plaintext)

---

## Installation

```bash
composer require lzpeng/gaara-hyperf-2fa
```

> **Prerequisite**: `lzpeng/gaara-hyperf` must already be installed and configured.

---

## Quick Start

### 1. Implement user interfaces

Depending on the 2FA methods you want to support, implement the corresponding interface on your User model.

**TOTP**

```php
use GaaraHyperf\TwoFactor\TotpUserInterface;

class User implements TotpUserInterface
{
    public function getIdentifier(): string              { return $this->id; }
    public function isTwoFactorEnabled(): bool           { return $this->two_factor_enabled; }
    public function getPreferredTwoFactorMethod(): string { return 'totp'; }
    public function getTwoFactorSecret(): string         { return $this->totp_secret; } // Base32-encoded
}
```

**Email OTP**

```php
use GaaraHyperf\TwoFactor\EmailOtpUserInterface;

class User implements EmailOtpUserInterface
{
    public function isTwoFactorEnabled(): bool           { return $this->two_factor_enabled; }
    public function getPreferredTwoFactorMethod(): string { return 'email'; }
    public function getTwoFactorEmail(): string          { return $this->email; }
}
```

**SMS OTP**

```php
use GaaraHyperf\TwoFactor\SmsOtpUserInterface;

class User implements SmsOtpUserInterface
{
    public function isTwoFactorEnabled(): bool           { return $this->two_factor_enabled; }
    public function getPreferredTwoFactorMethod(): string { return 'sms'; }
    public function getTwoFactorPhoneNumber(): string    { return $this->phone; }
}
```

### 2. Register the `two_factor` authenticator in a Guard

Edit `config/autoload/gaara.php`:

```php
'guards' => [
    'api' => [
        'user_provider' => [...],
        'authenticators' => [
            'json_login' => [
                'login_path'     => '/login',
                'username_field' => 'email',
                'password_field' => 'password',
            ],
            'two_factor' => [                           // add this block
                'verify_path'        => '/two-factor/verify',
                'challenge_ttl'      => 300,            // seconds
                'code_field'         => 'code',
                'challenge_id_field' => 'challenge_id',
                'storage'            => ['type' => 'session'],
                'methods' => [
                    'totp' => ['leeway' => 30],         // time tolerance in seconds
                ],
            ],
        ],
    ],
],
```

### 3. Bind senders (Email / SMS)

For Email or SMS methods, bind an implementation in the container:

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

## Authentication Flow

```
POST /login
 └─ JsonLoginAuthenticator validates credentials
     └─ [success] → AuthenticationSuccessEvent
         └─ TwoFactorEnforcementListener checks whether 2FA is enabled for the user
             ├─ disabled → issue token normally (no impact on existing flow)
             └─ enabled  → initialise challenge (send TOTP / Email / SMS code)
                            replace token with TwoFactorPendingToken
                            return HTTP 200 { code:1, message:"two_factor_required", data:{challenge_id, ...hint} }

POST /two-factor/verify  { challenge_id, code }
 └─ TwoFactorAuthenticator verifies the code
     ├─ invalid / expired → 401 Unauthorized
     └─ valid             → delete challenge, issue final AuthenticatedToken
```

### Login response samples

**2FA required — Email:**

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

**2FA required — SMS:**

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

**TOTP (no hint — user reads code from Authenticator app):**

```json
{
  "code": 1,
  "message": "two_factor_required",
  "data": { "challenge_id": "550e8400-e29b-41d4-a716-446655440000" }
}
```

### Verification request

```json
POST /two-factor/verify
{
  "challenge_id": "550e8400-e29b-41d4-a716-446655440000",
  "code": "123456"
}
```

---

## Configuration Reference

| Key | Type | Default | Description |
|---|---|---|---|
| `verify_path` | string | `/two-factor/verify` | POST endpoint for code verification |
| `challenge_ttl` | int | `300` | Challenge validity in seconds |
| `code_field` | string | `code` | Request body field name for the OTP code |
| `challenge_id_field` | string | `challenge_id` | Request body field name for the challenge ID |
| `storage.type` | string | `session` | Storage driver: `session` or `redis` |
| `storage.ttl` | int | same as `challenge_ttl` | Redis key TTL in seconds |
| `methods` | array | `['totp' => []]` | Enabled methods and their options |
| `methods.totp.leeway` | int | `30` | TOTP clock skew tolerance in seconds |
| `methods.email.code_length` | int | `6` | Email OTP code length |
| `methods.sms.code_length` | int | `6` | SMS OTP code length |

**Redis storage:**

```php
'storage' => [
    'type' => 'redis',
    'ttl'  => 300,
],
```

> Requires `hyperf/redis`: `composer require hyperf/redis`

---

## Custom Methods

Implement `TwoFactorMethodInterface` and register it with the method registry:

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

Custom methods should be registered by extending `TwoFactorAuthenticatorBuilder::buildMethod()` or by registering directly with `TwoFactorMethodRegistry` in the application layer.

---

## User Interface Reference

| Interface | Methods | Used for |
|---|---|---|
| `TwoFactorAwareUserInterface` | `isTwoFactorEnabled()`, `getPreferredTwoFactorMethod()` | All methods (base interface) |
| `TotpUserInterface` | + `getTwoFactorSecret(): string` | TOTP |
| `EmailOtpUserInterface` | + `getTwoFactorEmail(): string` | Email OTP |
| `SmsOtpUserInterface` | + `getTwoFactorPhoneNumber(): string` | SMS OTP |

---

## Security Notes

- **Single-use challenges**: deleted immediately after a successful verification — replay attacks are prevented.
- **Hashed code storage**: Email/SMS plaintext codes are never written to storage; only SHA-256 hashes are stored.
- **TTL enforcement**: both Session and Redis storage respect the configured TTL.
- **Loop prevention**: `TwoFactorEnforcementListener` ignores success events fired by `TwoFactorAuthenticator`, preventing infinite loops.
- **Non-invasive**: users with `isTwoFactorEnabled() === false` are completely unaffected.

---

## License

MIT
