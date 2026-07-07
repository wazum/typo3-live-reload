<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Middleware\DisablePageCacheMiddleware;
use Wazum\LiveReload\Tests\Support\SwitchesApplicationContext;

final class DisablePageCacheMiddlewareTest extends FunctionalTestCase
{
    use SwitchesApplicationContext;

    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected function tearDown(): void
    {
        $this->restoreApplicationContext();
        parent::tearDown();
    }

    #[Test]
    public function disablesPageCachingInDevelopmentContext(): void
    {
        $this->switchApplicationContext('Development');
        $cacheInstruction = new CacheInstruction();

        $this->process((new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $cacheInstruction));

        self::assertFalse($cacheInstruction->isCachingAllowed());
    }

    #[Test]
    public function providesTheCacheInstructionWhenMissing(): void
    {
        $this->switchApplicationContext('Development');
        $seenInstruction = null;
        $handler = new class($seenInstruction) implements RequestHandlerInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen = $request->getAttribute('frontend.cache.instruction');

                return new HtmlResponse('');
            }
        };

        $this->get(DisablePageCacheMiddleware::class)
            ->process(new ServerRequest('https://example.org/', 'GET'), $handler);

        self::assertInstanceOf(CacheInstruction::class, $seenInstruction);
        self::assertFalse($seenInstruction->isCachingAllowed());
    }

    #[Test]
    public function leavesCachingAloneWhenDevelopmentIsNotAnActiveContext(): void
    {
        $this->switchApplicationContext('Development');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['activeContexts'] = 'Production/Staging';
        $cacheInstruction = new CacheInstruction();

        $this->process((new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $cacheInstruction));

        self::assertTrue($cacheInstruction->isCachingAllowed());
    }

    #[Test]
    public function leavesCachingAloneWhenFileReloadIsDisabled(): void
    {
        $this->switchApplicationContext('Development');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['fileReload'] = '0';
        $cacheInstruction = new CacheInstruction();

        $this->process((new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $cacheInstruction));

        self::assertTrue($cacheInstruction->isCachingAllowed());
    }

    #[Test]
    public function leavesCachingAloneOutsideDevelopmentContext(): void
    {
        $this->switchApplicationContext('Testing');
        $cacheInstruction = new CacheInstruction();

        $this->process((new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('frontend.cache.instruction', $cacheInstruction));

        self::assertTrue($cacheInstruction->isCachingAllowed());
    }

    private function process(ServerRequestInterface $request): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse('');
            }
        };
        $this->get(DisablePageCacheMiddleware::class)->process($request, $handler);
    }
}
