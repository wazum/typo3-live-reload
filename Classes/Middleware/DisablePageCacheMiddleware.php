<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use Wazum\LiveReload\Configuration\ExtensionSettings;

/**
 * A cached page response skips the Fluid render, so no file tags would be
 * captured and a tab served from cache would stop reacting to template
 * edits. Every development render therefore stays fresh.
 */
final class DisablePageCacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionSettings $settings,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->settings->fileCaptureActive()) {
            return $handler->handle($request);
        }

        $cacheInstruction = $request->getAttribute('frontend.cache.instruction') ?? new CacheInstruction();
        $cacheInstruction->disableCache('EXT:live_reload: fresh renders keep file tags accurate');

        return $handler->handle($request->withAttribute('frontend.cache.instruction', $cacheInstruction));
    }
}
