<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\LiveReload\Collector\BroadcastTagCollector;
use Wazum\LiveReload\Tests\Support\RecordingBroadcaster;
use Wazum\LiveReload\Tests\Support\RecordingDetacher;

final class CacheFlushListenerTest extends FunctionalTestCase
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
    }

    #[Test]
    public function flushingCachesOnTheCommandLineBroadcastsAReloadOfEveryTab(): void
    {
        $this->get(EventDispatcherInterface::class)->dispatch(new CacheFlushEvent(['pages', 'system']));

        GeneralUtility::makeInstance(BroadcastTagCollector::class)->flush();

        self::assertSame([[BroadcastTagCollector::RELOAD_ALL]], $this->broadcaster->received);
    }

    #[Test]
    public function flushingCachesOutsideActiveContextsBroadcastsNothing(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['live_reload']['activeContexts'] = 'Development';

        $this->get(EventDispatcherInterface::class)->dispatch(new CacheFlushEvent(['pages', 'system']));

        GeneralUtility::makeInstance(BroadcastTagCollector::class)->flush();

        self::assertSame([], $this->broadcaster->received);
    }
}
