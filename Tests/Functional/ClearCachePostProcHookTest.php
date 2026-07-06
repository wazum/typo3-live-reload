<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Collector\BroadcastTagCollector;
use Wazum\LiveReload\Event\ModifyBroadcastTagsEvent;
use Wazum\LiveReload\Hook\ClearCachePostProcHook;
use Wazum\LiveReload\Tests\Support\RecordingBroadcaster;
use Wazum\LiveReload\Tests\Support\RecordingDetacher;

final class ClearCachePostProcHookTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['typo3/cms-adminpanel'];

    protected array $testExtensionsToLoad = ['wazum/typo3-live-reload'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'live_reload' => ['activeContexts' => 'Testing'],
        ],
    ];

    private RecordingBroadcaster $broadcaster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->broadcaster = new RecordingBroadcaster();
        GeneralUtility::setSingletonInstance(
            BroadcastTagCollector::class,
            new BroadcastTagCollector($this->broadcaster, new RecordingDetacher()),
        );
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PagesAndContent.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    #[Test]
    public function contentElementSaveCollectsCoreFlushTags(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(
            ['tt_content' => [5 => ['header' => 'Changed header']]],
            [],
        );
        $dataHandler->process_datamap();

        $collector = GeneralUtility::makeInstance(BroadcastTagCollector::class);
        $collector->flush();

        $broadcastTags = $this->broadcaster->received[0] ?? [];
        self::assertContains('tt_content_5', $broadcastTags);
        self::assertContains('tt_content', $broadcastTags);
        self::assertContains('pageId_42', $broadcastTags);
    }

    #[Test]
    public function clearingASinglePageCacheBroadcastsThePageTag(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], []);
        $dataHandler->clear_cacheCmd('42');

        $collector = GeneralUtility::makeInstance(BroadcastTagCollector::class);
        $collector->flush();

        self::assertSame([['pageId_42']], $this->broadcaster->received);
    }

    #[Test]
    public function aThrowingEventListenerDoesNotBreakTheSave(): void
    {
        $collectorBroadcaster = new RecordingBroadcaster();
        $collector = new BroadcastTagCollector($collectorBroadcaster, new RecordingDetacher());
        $eventDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('listener failure', 1751700000);
            }
        };
        $hook = new ClearCachePostProcHook(
            $this->get(\Wazum\LiveReload\Configuration\ExtensionSettings::class),
            $collector,
            $eventDispatcher,
        );

        $hook->postProcessClearCache(
            ['table' => 'tt_content', 'uid' => 5, 'uid_page' => 42, 'tags' => ['tt_content_5' => true]],
            GeneralUtility::makeInstance(DataHandler::class),
        );
        $collector->flush();

        self::assertSame([], $collectorBroadcaster->received);
    }

    #[Test]
    public function eventListenersCanAddBroadcastTags(): void
    {
        $collectorBroadcaster = new RecordingBroadcaster();
        $collector = new BroadcastTagCollector($collectorBroadcaster, new RecordingDetacher());
        $eventDispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof ModifyBroadcastTagsEvent && $event->getTable() === 'tx_news_domain_model_news') {
                    $event->addTags('tx_news_uid_' . $event->getUid(), 'tx_news_pid_' . $event->getUidPage());
                }

                return $event;
            }
        };
        $hook = new ClearCachePostProcHook(
            $this->get(\Wazum\LiveReload\Configuration\ExtensionSettings::class),
            $collector,
            $eventDispatcher,
        );

        $hook->postProcessClearCache(
            [
                'table' => 'tx_news_domain_model_news',
                'uid' => 7,
                'uid_page' => 88,
                'tags' => ['tx_news_domain_model_news' => true, 'tx_news_domain_model_news_7' => true, 'pageId_88' => true],
            ],
            GeneralUtility::makeInstance(DataHandler::class),
        );
        $collector->flush();

        self::assertSame(
            [['tx_news_domain_model_news', 'tx_news_domain_model_news_7', 'pageId_88', 'tx_news_uid_7', 'tx_news_pid_88']],
            $collectorBroadcaster->received,
        );
    }
}
