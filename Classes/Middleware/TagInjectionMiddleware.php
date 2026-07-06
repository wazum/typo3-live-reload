<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Cache\CacheDataCollectorInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\ConsumableNonce;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Middleware\PolicyBag;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Wazum\LiveReload\Broadcast\BroadcastLogInterface;
use Wazum\LiveReload\Collector\RenderedFileCollector;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Resolver\DevServerUrlResolver;

final class TagInjectionMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CLIENT_SCRIPT = 'EXT:live_reload/Resources/Public/JavaScript/poll-client.js';

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly DevServerUrlResolver $devServerUrlResolver,
        private readonly BroadcastLogInterface $broadcastLog,
        private readonly Context $context,
        private readonly RenderedFileCollector $renderedFileCollector,
        private readonly ?object $systemResourceFactory = null,
        private readonly ?object $systemResourcePublisher = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if (!$this->settings->contextAllowed()) {
            return $response;
        }

        try {
            return $this->inject($request, $response);
        } catch (Throwable $exception) {
            $this->logger?->warning('Live reload injection failed', ['exception' => $exception]);

            return $response;
        }
    }

    private function inject(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $html = (string)$response->getBody();
        $insertPosition = $this->insertPosition($html);
        if ($insertPosition === null) {
            return $response;
        }

        $snippet = $this->snippet($request, $html);
        if ($snippet === null) {
            return $response;
        }

        $this->declareNonceUsage($request);
        $body = new Stream('php://temp', 'rw');
        $body->write(substr($html, 0, $insertPosition) . $snippet . substr($html, $insertPosition));

        return $response->withBody($body);
    }

    private function snippet(ServerRequestInterface $request, string $html): ?string
    {
        if ($this->settings->developmentContext()) {
            return $this->viteSnippet($request, $html) ?? $this->pollSnippet($request, $html);
        }
        if (!$this->backendUserLoggedIn()) {
            return null;
        }

        return $this->pollSnippet($request, $html);
    }

    private function viteSnippet(ServerRequestInterface $request, string $html): ?string
    {
        $devServerUrl = $this->devServerUrlResolver->resolve($request);
        if ($devServerUrl === null) {
            return null;
        }

        $configuration = $this->configuration($request, ['transport' => 'vite']);
        $nonceValue = $this->nonceValue($request);
        $nonceAttribute = $this->nonceAttribute($nonceValue);
        $moduleUrl = htmlspecialchars($devServerUrl . '/@id/virtual:live-reload');
        $snippet = '<script' . $nonceAttribute . '>window.__liveReload = ' . $configuration . '</script>'
            . '<script type="module" src="' . $moduleUrl . '"' . $nonceAttribute . '></script>';

        return $this->withCspNonceMeta($snippet, $nonceValue, $html);
    }

    private function pollSnippet(ServerRequestInterface $request, string $html): string
    {
        $configuration = $this->configuration($request, [
            'transport' => 'poll',
            'endpoint' => PollEndpointMiddleware::PATH,
            'interval' => $this->settings->pollInterval(),
            'sequence' => $this->broadcastLog->latestSequence(),
        ]);
        $nonceValue = $this->nonceValue($request);
        $nonceAttribute = $this->nonceAttribute($nonceValue);
        $scriptUrl = htmlspecialchars($this->clientScriptUrl($request));
        $snippet = '<script' . $nonceAttribute . '>window.__liveReload = ' . $configuration . '</script>'
            . '<script defer src="' . $scriptUrl . '"' . $nonceAttribute . '></script>';

        return $this->withCspNonceMeta($snippet, $nonceValue, $html);
    }

    /**
     * @param array<string, string|int> $transportConfiguration
     */
    private function configuration(ServerRequestInterface $request, array $transportConfiguration): string
    {
        return json_encode(
            array_merge(
                ['tags' => $this->tags($request), 'mode' => $this->mode($request)],
                $transportConfiguration,
            ),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG,
        );
    }

    private function clientScriptUrl(ServerRequestInterface $request): string
    {
        if ($this->systemResourceFactory === null || $this->systemResourcePublisher === null) {
            return PathUtility::getPublicResourceWebPath(self::CLIENT_SCRIPT);
        }

        return (string)$this->systemResourcePublisher->generateUri(
            $this->systemResourceFactory->createPublicResource(self::CLIENT_SCRIPT),
            $request,
        );
    }

    private function withCspNonceMeta(string $snippet, ?string $nonceValue, string $html): string
    {
        if ($nonceValue === null || str_contains($html, 'property="csp-nonce"')) {
            return $snippet;
        }

        return '<meta property="csp-nonce" nonce="' . htmlspecialchars($nonceValue) . '">' . $snippet;
    }

    private function nonceAttribute(?string $nonceValue): string
    {
        return $nonceValue === null ? '' : ' nonce="' . htmlspecialchars($nonceValue) . '"';
    }

    private function backendUserLoggedIn(): bool
    {
        return (bool)$this->context->getPropertyFromAspect('backend.user', 'isLoggedIn', false);
    }

    private function declareNonceUsage(ServerRequestInterface $request): void
    {
        if ($request->getAttribute('nonce') === null) {
            return;
        }

        $policyBag = $request->getAttribute('csp.policyBag');
        if ($policyBag instanceof PolicyBag) {
            $policyBag->behavior->useNonce = true;
        }
    }

    private function insertPosition(string $html): ?int
    {
        $headEnd = stripos($html, '</head>');
        if ($headEnd !== false) {
            return $headEnd;
        }
        $bodyEnd = stripos($html, '</body>');

        return $bodyEnd === false ? null : $bodyEnd;
    }

    private function mode(ServerRequestInterface $request): string
    {
        $override = $request->getAttribute('live_reload.mode');

        return match ($override) {
            'tagged', 'always', 'paused' => $override,
            default => $this->settings->reloadMode(),
        };
    }

    /**
     * @return array<string>
     */
    private function tags(ServerRequestInterface $request): array
    {
        $tags = [];
        $collector = $request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollectorInterface) {
            foreach ($collector->getCacheTags() as $cacheTag) {
                $tags[$cacheTag->name] = true;
            }
        }

        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            $tags['pageId_' . $pageInformation->getId()] = true;
        }

        foreach ($this->renderedFileCollector->fileTags(Environment::getProjectPath()) as $fileTag) {
            $tags[$fileTag] = true;
        }

        return array_keys($tags);
    }

    private function nonceValue(ServerRequestInterface $request): ?string
    {
        $nonce = $request->getAttribute('nonce');

        return $nonce instanceof ConsumableNonce ? $nonce->consume() : null;
    }
}
