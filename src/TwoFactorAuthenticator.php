<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor;

use GaaraHyperf\Authenticator\AbstractAuthenticator;
use GaaraHyperf\Authenticator\AuthenticationFailureHandlerInterface;
use GaaraHyperf\Authenticator\AuthenticationSuccessHandlerInterface;
use GaaraHyperf\Exception\AuthenticationException;
use GaaraHyperf\Passport\Passport;
use GaaraHyperf\TwoFactor\ChallengeStorage\ChallengeStorageInterface;
use GaaraHyperf\TwoFactor\Totp\TotpVerifierInterface;
use GaaraHyperf\UserProvider\UserProviderInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 双因素认证器.
 *
 * 处理 TOTP 验证端点（verify_path）的请求：
 *   1. 根据请求中的 challenge_id 检索挑战数据
 *   2. 校验挑战 TTL
 *   3. 使用 TOTP 验证器验证用户提交的验证码
 *   4. 验证通过后返回 Passport，由 Guard 创建最终的 AuthenticatedToken
 */
class TwoFactorAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private string $verifyPath,
        private ChallengeStorageInterface $challengeStorage,
        private TotpVerifierInterface $totpVerifier,
        private UserProviderInterface $userProvider,
        private int $challengeTtl,
        private string $codeField,
        private string $challengeIdField,
        ?AuthenticationSuccessHandlerInterface $successHandler = null,
        ?AuthenticationFailureHandlerInterface $failureHandler = null,
    ) {
        parent::__construct($successHandler, $failureHandler);
    }

    public function supports(ServerRequestInterface $request): bool
    {
        return $request->getMethod() === 'POST'
            && $request->getUri()->getPath() === $this->verifyPath;
    }

    public function authenticate(ServerRequestInterface $request): Passport
    {
        $body = (array) $request->getParsedBody();

        $challengeId = (string) ($body[$this->challengeIdField] ?? '');
        $code = (string) ($body[$this->codeField] ?? '');

        if ($challengeId === '') {
            throw new AuthenticationException('Missing challenge_id');
        }

        if ($code === '') {
            throw new AuthenticationException('Missing two-factor code');
        }

        // 检索挑战
        $challenge = $this->challengeStorage->get($challengeId);
        if ($challenge === null) {
            throw new AuthenticationException('Invalid or expired challenge');
        }

        // 验证 TTL
        if ($challenge->isExpired($this->challengeTtl)) {
            $this->challengeStorage->delete($challengeId);
            throw new AuthenticationException('Two-factor challenge has expired');
        }

        // 加载用户
        $user = $this->userProvider->findByIdentifier($challenge->userIdentifier);
        if ($user === null) {
            $this->challengeStorage->delete($challengeId);
            throw new AuthenticationException(
                message: 'User not found',
                userIdentifier: $challenge->userIdentifier,
            );
        }

        if (! $user instanceof TwoFactorAwareUserInterface) {
            $this->challengeStorage->delete($challengeId);
            throw new AuthenticationException(
                message: 'User does not support two-factor authentication',
                userIdentifier: $challenge->userIdentifier,
            );
        }

        // 验证 TOTP 码
        if (! $this->totpVerifier->verify($user->getTwoFactorSecret(), $code)) {
            throw new AuthenticationException(
                message: 'Invalid two-factor code',
                userIdentifier: $challenge->userIdentifier,
            );
        }

        // 验证通过，删除挑战（一次性使用）
        $this->challengeStorage->delete($challengeId);

        return new Passport(
            $challenge->userIdentifier,
            fn () => $user,
        );
    }

    /**
     * 2FA 验证属于交互式认证，验证成功后需要持久化令牌（如写入 Session）.
     */
    public function isInteractive(): bool
    {
        return true;
    }
}
