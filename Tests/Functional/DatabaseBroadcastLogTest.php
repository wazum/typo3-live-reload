<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Broadcast\DatabaseBroadcastLog;
use Wazum\LiveReload\Configuration\ExtensionSettings;

final class DatabaseBroadcastLogTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'live_reload' => ['activeContexts' => 'Testing'],
        ],
    ];

    #[Test]
    public function sequencesAreZeroWhenTheLogIsEmpty(): void
    {
        $log = $this->log();

        self::assertSame(0, $log->latestSequence());
        self::assertSame(0, $log->oldestSequence());
    }

    #[Test]
    public function appendStoresTagsWithMonotonicallyIncreasingSequences(): void
    {
        $log = $this->log();

        $log->append(['pageId_42', 'tt_content_5']);
        $log->append(['pageId_7']);

        self::assertSame(2, $log->latestSequence());
        self::assertSame(1, $log->oldestSequence());
        self::assertSame(
            [
                ['sequence' => 1, 'tags' => ['pageId_42', 'tt_content_5']],
                ['sequence' => 2, 'tags' => ['pageId_7']],
            ],
            $log->since(0),
        );
    }

    #[Test]
    public function sinceReturnsOnlyRowsAfterTheGivenSequence(): void
    {
        $log = $this->log();
        $log->append(['pageId_1']);
        $log->append(['pageId_2']);
        $log->append(['pageId_3']);

        self::assertSame(
            [
                ['sequence' => 3, 'tags' => ['pageId_3']],
            ],
            $log->since(2),
        );
        self::assertSame([], $log->since(3));
    }

    #[Test]
    public function sinceCapsAtOneHundredRows(): void
    {
        $log = $this->log();
        foreach (range(1, 105) as $index) {
            $log->append(['pageId_' . $index]);
        }

        $rows = $log->since(0);

        self::assertCount(100, $rows);
        self::assertSame(1, $rows[0]['sequence']);
        self::assertSame(100, $rows[99]['sequence']);
    }

    #[Test]
    public function appendPrunesRowsOlderThanTheRetentionWindow(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_livereload_broadcast');
        $connection->insert('tx_livereload_broadcast', [
            'tags' => '["pageId_old"]',
            'crdate' => time() - 3600,
        ]);
        $log = $this->log();

        $log->append(['pageId_new']);

        self::assertSame(2, $log->oldestSequence());
        self::assertSame(
            [
                ['sequence' => 2, 'tags' => ['pageId_new']],
            ],
            $log->since(0),
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
