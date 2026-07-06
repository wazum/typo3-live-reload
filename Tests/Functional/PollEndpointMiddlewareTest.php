<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Broadcast\DatabaseBroadcastLog;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Middleware\PollEndpointMiddleware;
use Wazum\LiveReload\Tests\Support\SwitchesApplicationContext;

final class PollEndpointMiddlewareTest extends FunctionalTestCase
{
    use SwitchesApplicationContext;

    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'live_reload' => ['activeContexts' => 'Development, Testing'],
        ],
    ];

    protected function tearDown(): void
    {
        $this->restoreApplicationContext();
        parent::tearDown();
    }

    #[Test]
    public function passesThroughRequestsForOtherPaths(): void
    {
        $this->logInBackendUser();

        $response = $this->poll('/some/page', ['since' => '0']);

        self::assertSame('handler', (string)$response->getBody());
    }

    #[Test]
    public function passesThroughUntouchedWhenTheContextIsNotAllowed(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['activeContexts'] = 'Development';

        $response = $this->poll('/__live-reload/poll', ['since' => '0']);

        self::assertSame('handler', (string)$response->getBody());
    }

    #[Test]
    public function answersBareNotFoundWithoutALoggedInBackendUserAspectOutsideDevelopment(): void
    {
        $response = $this->poll('/__live-reload/poll', ['since' => '0']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string)$response->getBody());
    }

    #[Test]
    public function answersBareNotFoundWhenTheBackendUserAspectIsNotLoggedIn(): void
    {
        $this->get(Context::class)->setAspect('backend.user', new UserAspect());

        $response = $this->poll('/__live-reload/poll', ['since' => '0']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string)$response->getBody());
    }

    #[Test]
    public function answersAnonymousRequestsInDevelopment(): void
    {
        $this->switchApplicationContext('Development');

        $response = $this->poll('/__live-reload/poll', ['since' => '0']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['sequence' => 0, 'broadcasts' => []], $this->payload($response));
    }

    #[Test]
    public function sendsJsonWithNoStorePrivateCacheControl(): void
    {
        $this->logInBackendUser();

        $response = $this->poll('/__live-reload/poll', ['since' => '0']);

        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store, private', $response->getHeaderLine('Cache-Control'));
    }

    #[Test]
    public function deliversTheFirstBroadcastWhenTheLogStartedEmpty(): void
    {
        $this->logInBackendUser();

        $emptyResponse = $this->poll('/__live-reload/poll', ['since' => '0']);
        self::assertSame(['sequence' => 0, 'broadcasts' => []], $this->payload($emptyResponse));

        $this->log()->append(['pageId_42']);

        $response = $this->poll('/__live-reload/poll', ['since' => '0']);
        self::assertSame(
            ['sequence' => 1, 'broadcasts' => [['sequence' => 1, 'tags' => ['pageId_42']]]],
            $this->payload($response),
        );
    }

    #[Test]
    public function answersBadRequestForMissingOrMalformedSince(): void
    {
        $this->logInBackendUser();

        self::assertSame(400, $this->poll('/__live-reload/poll', [])->getStatusCode());
        self::assertSame(400, $this->poll('/__live-reload/poll', ['since' => 'abc'])->getStatusCode());
        self::assertSame(400, $this->poll('/__live-reload/poll', ['since' => '-1'])->getStatusCode());
    }

    #[Test]
    public function returnsBroadcastsAfterTheGivenSequence(): void
    {
        $this->logInBackendUser();
        $this->log()->append(['pageId_1']);
        $this->log()->append(['pageId_2']);
        $this->log()->append(['pageId_3']);

        $response = $this->poll('/__live-reload/poll', ['since' => '1']);

        self::assertSame(
            [
                'sequence' => 3,
                'broadcasts' => [
                    ['sequence' => 2, 'tags' => ['pageId_2']],
                    ['sequence' => 3, 'tags' => ['pageId_3']],
                ],
            ],
            $this->payload($response),
        );
    }

    #[Test]
    public function returnsEmptyBroadcastsWhenSinceEqualsTheLatestSequence(): void
    {
        $this->logInBackendUser();
        $this->log()->append(['pageId_1']);

        $response = $this->poll('/__live-reload/poll', ['since' => '1']);

        self::assertSame(['sequence' => 1, 'broadcasts' => []], $this->payload($response));
    }

    #[Test]
    public function answersStaleWhenSinceIsBeyondTheLatestSequence(): void
    {
        $this->logInBackendUser();
        $this->log()->append(['pageId_1']);

        $response = $this->poll('/__live-reload/poll', ['since' => '99']);

        self::assertSame(['sequence' => 1, 'stale' => true], $this->payload($response));
    }

    #[Test]
    public function answersStaleWhenRequestedRowsWereAlreadyPruned(): void
    {
        $this->logInBackendUser();
        $this->log()->append(['pageId_1']);
        $this->log()->append(['pageId_2']);
        $this->log()->append(['pageId_3']);
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_livereload_broadcast');
        $connection->delete('tx_livereload_broadcast', ['uid' => 1]);
        $connection->delete('tx_livereload_broadcast', ['uid' => 2]);

        $response = $this->poll('/__live-reload/poll', ['since' => '1']);

        self::assertSame(['sequence' => 3, 'stale' => true], $this->payload($response));
    }

    #[Test]
    public function answersStaleWhenTheGapExceedsTheRowCap(): void
    {
        $this->logInBackendUser();
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_livereload_broadcast');
        foreach (range(1, 102) as $index) {
            $connection->insert('tx_livereload_broadcast', [
                'tags' => '["pageId_' . $index . '"]',
                'crdate' => time(),
            ]);
        }

        $response = $this->poll('/__live-reload/poll', ['since' => '1']);

        self::assertSame(['sequence' => 102, 'stale' => true], $this->payload($response));
    }

    #[Test]
    public function cursorNeverFallsBehindTheReturnedBroadcasts(): void
    {
        $this->switchApplicationContext('Development');
        // Simulates a row inserted between the latestSequence() read and the
        // since() query: the log already returns sequence 2 while the earlier
        // cursor read only saw 1. The response cursor must be 2, or the client
        // would receive sequence 2 again on its next poll.
        $raceyLog = new class implements \Wazum\LiveReload\Broadcast\BroadcastLogInterface {
            public function append(array $tags): void
            {
            }

            public function since(int $sequence): array
            {
                return [
                    ['sequence' => 1, 'tags' => ['pageId_1']],
                    ['sequence' => 2, 'tags' => ['pageId_2']],
                ];
            }

            public function latestSequence(): int
            {
                return 1;
            }

            public function oldestSequence(): int
            {
                return 1;
            }
        };
        $middleware = new PollEndpointMiddleware(
            $this->get(ExtensionSettings::class),
            $raceyLog,
            $this->get(Context::class),
        );

        $request = (new ServerRequest('https://example.org' . PollEndpointMiddleware::PATH, 'GET'))
            ->withQueryParams(['since' => '0']);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse('handler');
            }
        };
        $payload = $this->payload($middleware->process($request, $handler));

        self::assertSame(2, $payload['sequence']);
        self::assertCount(2, $payload['broadcasts']);
    }

    /**
     * @param array<string, string> $queryParameters
     */
    private function poll(string $path, array $queryParameters): ResponseInterface
    {
        $request = (new ServerRequest('https://example.org' . $path, 'GET'))
            ->withQueryParams($queryParameters);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse('handler');
            }
        };

        return $this->get(PollEndpointMiddleware::class)->process($request, $handler);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
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
