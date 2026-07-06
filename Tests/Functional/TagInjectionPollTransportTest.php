<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\ConsumableNonce;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Broadcast\DatabaseBroadcastLog;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Middleware\TagInjectionMiddleware;

final class TagInjectionPollTransportTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'live_reload' => ['activeContexts' => 'Testing'],
        ],
    ];

    #[Test]
    public function injectsPollConfigurationAndClientScriptForLoggedInBackendUsers(): void
    {
        $this->logInBackendUser();
        $log = $this->log();
        $log->append(['pageId_1']);
        $log->append(['pageId_2']);

        $response = $this->process($this->request(), $this->htmlHandler('<html><head><title>t</title></head><body>x</body></html>'));

        $html = (string)$response->getBody();
        $headEnd = strpos($html, '</head>');
        $configPosition = strpos($html, 'window.__liveReload');
        self::assertNotFalse($configPosition);
        self::assertLessThan($headEnd, $configPosition);
        self::assertStringContainsString('"tags":["tt_content_5","pageId_42"]', $html);
        self::assertStringContainsString('"mode":"tagged"', $html);
        self::assertStringContainsString('"transport":"poll"', $html);
        self::assertStringContainsString('"endpoint":"\/__live-reload\/poll"', $html);
        self::assertStringContainsString('"interval":3000', $html);
        self::assertStringContainsString('"sequence":2', $html);
        self::assertMatchesRegularExpression('#<script defer src="[^"]*poll-client\.js[^"]*"></script>#', $html);
    }

    #[Test]
    public function skipsInjectionWithoutALoggedInBackendUser(): void
    {
        $html = '<html><head></head><body></body></html>';

        $response = $this->process($this->request(), $this->htmlHandler($html));

        self::assertSame($html, (string)$response->getBody());
    }

    #[Test]
    public function skipsInjectionWhenTheBackendUserAspectIsNotLoggedIn(): void
    {
        $this->get(Context::class)->setAspect('backend.user', new UserAspect());
        $html = '<html><head></head><body></body></html>';

        $response = $this->process($this->request(), $this->htmlHandler($html));

        self::assertSame($html, (string)$response->getBody());
    }

    #[Test]
    public function appliesNonceToBothPollScriptsAndAddsCspNonceMeta(): void
    {
        $this->logInBackendUser();
        $nonce = new ConsumableNonce();
        $request = $this->request()->withAttribute('nonce', $nonce);

        $response = $this->process($request, $this->htmlHandler('<html><head></head><body></body></html>'));

        $html = (string)$response->getBody();
        self::assertSame(3, substr_count($html, 'nonce="' . $nonce->value . '"'));
        self::assertStringContainsString('<meta property="csp-nonce" nonce="' . $nonce->value . '"', $html);
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

    private function logInBackendUser(): void
    {
        $backendUser = new BackendUserAuthentication();
        $backendUser->user = ['uid' => 1];
        $this->get(Context::class)->setAspect('backend.user', new UserAspect($backendUser));
    }

    private function log(): DatabaseBroadcastLog
    {
        return new DatabaseBroadcastLog(
            $this->get(ConnectionPool::class),
            $this->get(ExtensionSettings::class),
        );
    }
}
