<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Throwable;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\ConsumableNonce;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Middleware\TagInjectionMiddleware;
use Wazum\LiveReload\Tests\Support\SwitchesApplicationContext;

final class TagInjectionMiddlewareTest extends FunctionalTestCase
{
    use SwitchesApplicationContext;

    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'live_reload' => [
                'activeContexts' => 'Development, Testing',
                'viteServerPublicUrl' => 'https://vite.example:5173',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->switchApplicationContext('Development');
    }

    protected function tearDown(): void
    {
        $this->restoreApplicationContext();
        parent::tearDown();
    }

    #[Test]
    public function injectsTagsAndClientModuleBeforeHeadEnd(): void
    {
        $response = $this->process($this->request(), $this->htmlHandler('<html><head><title>t</title></head><body>x</body></html>'));

        $html = (string)$response->getBody();
        $headEnd = strpos($html, '</head>');
        $configPosition = strpos($html, 'window.__liveReload');
        self::assertNotFalse($configPosition);
        self::assertLessThan($headEnd, $configPosition);
        self::assertStringContainsString('"tags":["tt_content_5","pageId_42"]', $html);
        self::assertStringContainsString('"mode":"tagged"', $html);
        self::assertStringContainsString('"transport":"vite"', $html);
        self::assertStringContainsString(
            '<script type="module" src="https://vite.example:5173/@id/virtual:live-reload"',
            $html,
        );
    }

    #[Test]
    public function adminPanelModeOverrideReplacesConfiguredMode(): void
    {
        $overridden = $this->process(
            $this->request()->withAttribute('live_reload.mode', 'paused'),
            $this->htmlHandler('<html><head></head><body></body></html>'),
        );
        self::assertStringContainsString('"mode":"paused"', (string)$overridden->getBody());

        $unknown = $this->process(
            $this->request()->withAttribute('live_reload.mode', 'bogus'),
            $this->htmlHandler('<html><head></head><body></body></html>'),
        );
        self::assertStringContainsString('"mode":"tagged"', (string)$unknown->getBody());
    }

    #[Test]
    public function addsPageIdTagFromPageInformationWhenCollectorMissesIt(): void
    {
        $collector = new CacheDataCollector();
        $pageInformation = new PageInformation();
        $pageInformation->setId(42);
        $request = (new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('frontend.cache.collector', $collector)
            ->withAttribute('frontend.page.information', $pageInformation);

        $response = $this->process($request, $this->htmlHandler('<html><head></head><body></body></html>'));

        self::assertStringContainsString('"tags":["pageId_42"]', (string)$response->getBody());
    }

    #[Test]
    public function appliesNonceAndCspNonceMeta(): void
    {
        $nonce = new ConsumableNonce();
        $request = $this->request()->withAttribute('nonce', $nonce);

        $response = $this->process($request, $this->htmlHandler('<html><head></head><body></body></html>'));

        $html = (string)$response->getBody();
        self::assertStringContainsString('nonce="' . $nonce->value . '"', $html);
        self::assertStringContainsString('<meta property="csp-nonce" nonce="' . $nonce->value . '"', $html);
    }

    #[Test]
    public function declaresNonceUsageOnThePolicyBag(): void
    {
        $nonce = new ConsumableNonce();
        $policyBag = $this->policyBagFor($nonce);
        $request = $this->request()
            ->withAttribute('nonce', $nonce)
            ->withAttribute('csp.policyBag', $policyBag);

        $this->process($request, $this->htmlHandler('<html><head></head><body></body></html>'));

        self::assertTrue($policyBag->behavior->useNonce);
    }

    #[Test]
    public function leavesPolicyBagUntouchedWhenNothingIsInjected(): void
    {
        $nonce = new ConsumableNonce();
        $policyBag = $this->policyBagFor($nonce);
        $request = $this->request()
            ->withAttribute('nonce', $nonce)
            ->withAttribute('csp.policyBag', $policyBag);

        $this->process($request, $this->htmlHandler('<div>fragment</div>'));

        self::assertNull($policyBag->behavior->useNonce);
    }

    #[Test]
    public function fallsBackToBodyEndWithoutHeadAndSkipsWithoutBothMarkers(): void
    {
        $withBodyOnly = $this->process($this->request(), $this->htmlHandler('<html><body>x</body></html>'));
        self::assertStringContainsString('window.__liveReload', (string)$withBodyOnly->getBody());

        $fragment = $this->process($this->request(), $this->htmlHandler('<div>fragment</div>'));
        self::assertSame('<div>fragment</div>', (string)$fragment->getBody());
    }

    #[Test]
    public function skipsNonHtmlResponses(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse(['head' => '</head>']);
            }
        };

        $response = $this->process($this->request(), $handler);

        self::assertStringNotContainsString('__liveReload', (string)$response->getBody());
    }

    #[Test]
    public function fallsBackToPollTransportWhenNoDevServerUrlIsResolvable(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['viteServerPublicUrl'] = '';

        $response = $this->process($this->request(), $this->htmlHandler('<html><head></head><body></body></html>'));

        $html = (string)$response->getBody();
        self::assertStringContainsString('"transport":"poll"', $html);
        self::assertStringContainsString('"endpoint":"\/__live-reload\/poll"', $html);
        self::assertMatchesRegularExpression('#<script defer src="[^"]*poll-client\.js[^"]*"></script>#', $html);
        self::assertStringNotContainsString('virtual:live-reload', $html);
    }

    private function policyBagFor(ConsumableNonce $nonce): \TYPO3\CMS\Core\Security\ContentSecurityPolicy\Middleware\PolicyBag
    {
        $constructorArguments = [
            \TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope::frontend(),
            new \TYPO3\CMS\Core\Type\Map(),
            new \TYPO3\CMS\Core\Security\ContentSecurityPolicy\Configuration\Behavior(),
            $nonce,
        ];
        $reflection = new ReflectionClass(\TYPO3\CMS\Core\Security\ContentSecurityPolicy\Middleware\PolicyBag::class);
        $additional = [];
        foreach (array_slice($reflection->getConstructor()->getParameters(), 4) as $parameter) {
            $additional[] = $this->instantiateWithDefaults($parameter->getType()->getName());
        }

        return new \TYPO3\CMS\Core\Security\ContentSecurityPolicy\Middleware\PolicyBag(...$constructorArguments, ...$additional);
    }

    private function instantiateWithDefaults(string $className): object
    {
        try {
            return $this->get($className);
        } catch (Throwable) {
        }
        $arguments = [];
        foreach ((new ReflectionClass($className))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isDefaultValueAvailable() || $parameter->isVariadic()) {
                break;
            }
            $arguments[] = $this->instantiateWithDefaults($parameter->getType()->getName());
        }

        return new $className(...$arguments);
    }

    private function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->get(TagInjectionMiddleware::class)->process($request, $handler);
    }

    private function request(): ServerRequestInterface
    {
        $collector = new CacheDataCollector();
        $collector->addCacheTags(new CacheTag('tt_content_5'), new CacheTag('pageId_42'));
        $request = new ServerRequest('https://example.org/', 'GET');

        return $request
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            ->withAttribute('frontend.cache.collector', $collector);
    }

    private function htmlHandler(string $html): RequestHandlerInterface
    {
        return new class($html) implements RequestHandlerInterface {
            public function __construct(private readonly string $html)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse($this->html);
            }
        };
    }
}
