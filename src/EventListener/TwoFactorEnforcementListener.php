<?php

declare(strict_types=1);

namespace GaaraHyperf\TwoFactor\EventListener;

use GaaraHyperf\Event\AuthenticationSuccessEvent;
use GaaraHyperf\TwoFactor\ChallengeStorage\ChallengeStorageInterface;
use GaaraHyperf\TwoFactor\ChallengeStorage\TwoFactorChallenge;
use GaaraHyperf\TwoFactor\TwoFactorAwareUserInterface;
use GaaraHyperf\TwoFactor\TwoFactorAuthenticator;
use GaaraHyperf\TwoFactor\TwoFactorPendingToken;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * 双因素认证强制执行监听器.
 *
 * 监听 AuthenticationSuccessEvent：
 *   - 若用户实现了 TwoFactorAwareUserInterface 且启用了 2FA，
 *     则将已认证令牌替换为 TwoFactorPendingToken，并返回需要二次验证的响应，
 *     阻止用户在未完成 TOTP 验证前访问受保护资源。
 *   - 若用户不需要 2FA（未实现接口或未启用），则直接放行，不影响原有认证流程。
 */
class TwoFactorEnforcementListener implements EventSubscriberInterface
{
    public function __construct(
        private ChallengeStorageInterface $challengeStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        // 跳过 2FA 自身的认证成功事件，避免循环
        if ($event->getAuthenticator() instanceof TwoFactorAuthenticator) {
            return;
        }

        $user = $event->getPassport()->getUser();

        // 用户未实现 2FA 接口或未开启 2FA，直接放行
        if (! $user instanceof TwoFactorAwareUserInterface) {
            return;
        }

        if (! $user->isTwoFactorEnabled()) {
            return;
        }

        // 生成挑战 ID 并存储挑战数据
        $challengeId = $this->generateChallengeId();
        $challenge = new TwoFactorChallenge(
            userIdentifier: $user->getIdentifier(),
            guardName: $event->getGuardName(),
            issuedAt: time(),
        );
        $this->challengeStorage->store($challengeId, $challenge);

        // 将令牌替换为待验证令牌，阻止访问受保护资源
        $event->setToken(new TwoFactorPendingToken(
            guardName: $event->getGuardName(),
            userIdentifier: $user->getIdentifier(),
            challengeId: $challengeId,
        ));

        // 覆盖响应，通知客户端需要进行 2FA 验证
        $body = json_encode([
            'code' => 1,
            'message' => 'two_factor_required',
            'data' => [
                'challenge_id' => $challengeId,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream($body));

        $event->setResponse($response);
    }

    private function generateChallengeId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
