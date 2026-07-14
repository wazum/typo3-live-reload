<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Broadcast\BroadcastLogInterface;
use Wazum\LiveReload\Broadcast\DatabaseBroadcastLog;
use Wazum\LiveReload\Broadcaster\BroadcastLogWriter;
use Wazum\LiveReload\Broadcaster\CompositeBroadcaster;
use Wazum\LiveReload\Broadcaster\ViteDevServerBroadcaster;
use Wazum\LiveReload\Configuration\ExtensionSettings;
use Wazum\LiveReload\Tests\Support\SwitchesApplicationContext;

final class CompositeBroadcasterTest extends FunctionalTestCase
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
    public function appendsToTheLogWithoutCallingTheViteDevServerOutsideDevelopment(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::never())->method('request');
        $log = $this->log();

        $this->broadcaster($requestFactory)->broadcast('pageId_42');

        self::assertSame([['sequence' => 1, 'tags' => ['pageId_42']]], $log->since(0));
    }

    #[Test]
    public function appendsToTheLogAndBroadcastsToTheViteDevServerInDevelopment(): void
    {
        $this->switchApplicationContext('Development');
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())->method('request');
        $log = $this->log();

        $this->broadcaster($requestFactory)->broadcast('pageId_42');

        self::assertSame([['sequence' => 1, 'tags' => ['pageId_42']]], $log->since(0));
    }

    #[Test]
    public function aFailingLogWriteDoesNotStopTheViteBroadcast(): void
    {
        $this->switchApplicationContext('Development');
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects(self::once())->method('request');
        $settings = $this->get(ExtensionSettings::class);
        $throwingLog = new class implements BroadcastLogInterface {
            public function append(array $tags): void
            {
                throw new RuntimeException('database gone', 1752500000);
            }

            public function since(int $sequence): array
            {
                return [];
            }

            public function latestSequence(): int
            {
                return 0;
            }

            public function oldestSequence(): int
            {
                return 0;
            }
        };
        $broadcaster = new CompositeBroadcaster(
            $settings,
            new BroadcastLogWriter($throwingLog),
            new ViteDevServerBroadcaster($settings, $requestFactory),
        );

        $broadcaster->broadcast('pageId_42');
    }

    private function broadcaster(RequestFactory $requestFactory): CompositeBroadcaster
    {
        $settings = $this->get(ExtensionSettings::class);

        return new CompositeBroadcaster(
            $settings,
            new BroadcastLogWriter($this->log()),
            new ViteDevServerBroadcaster($settings, $requestFactory),
        );
    }

    private function log(): DatabaseBroadcastLog
    {
        return new DatabaseBroadcastLog(
            $this->get(ConnectionPool::class),
            $this->get(ExtensionSettings::class),
        );
    }
}
