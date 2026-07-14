<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use Wazum\LiveReload\Broadcast\BroadcastLogInterface;
use Wazum\LiveReload\Configuration\ExtensionSettings;

final class PollEndpointMiddleware implements MiddlewareInterface
{
    public const PATH = '/__live-reload/poll';

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly BroadcastLogInterface $broadcastLog,
        private readonly Context $context,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== self::PATH || !$this->settings->contextAllowed()) {
            return $handler->handle($request);
        }

        if (!$this->settings->developmentContext() && !$this->backendUserLoggedIn()) {
            return new Response('php://temp', 404);
        }

        $since = $this->sinceParameter($request);
        if ($since === null) {
            return new Response('php://temp', 400);
        }

        return $this->jsonResponse($this->payload($since));
    }

    private function backendUserLoggedIn(): bool
    {
        return (bool)$this->context->getPropertyFromAspect('backend.user', 'isLoggedIn', false);
    }

    private function sinceParameter(ServerRequestInterface $request): ?int
    {
        $value = $request->getQueryParams()['since'] ?? null;
        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        return (int)$value;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $since): array
    {
        $latestSequence = $this->broadcastLog->latestSequence();
        if ($since > $latestSequence) {
            return ['sequence' => $latestSequence, 'stale' => true];
        }
        if ($since === $latestSequence) {
            return ['sequence' => $latestSequence, 'broadcasts' => []];
        }
        if ($since + 1 < $this->broadcastLog->oldestSequence()
            || $latestSequence - $since > BroadcastLogInterface::MAXIMUM_BATCH_SIZE
        ) {
            return ['sequence' => $latestSequence, 'stale' => true];
        }

        $broadcasts = $this->broadcastLog->since($since);
        // A broadcast can be appended between the latestSequence() read and the
        // since() query; the cursor must cover everything actually returned or
        // the client would receive the tail entries again on its next poll.
        $sequence = max([$latestSequence, ...array_column($broadcasts, 'sequence')]);

        return ['sequence' => $sequence, 'broadcasts' => $broadcasts];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload): ResponseInterface
    {
        return (new JsonResponse($payload))->withHeader('Cache-Control', 'no-store, private');
    }
}
