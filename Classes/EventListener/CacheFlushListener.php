<?php

declare(strict_types=1);

namespace Wazum\LiveReload\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\Event\CacheFlushEvent;
use Wazum\LiveReload\Collector\BroadcastTagCollector;
use Wazum\LiveReload\Configuration\ExtensionSettings;

#[AsEventListener(identifier: 'live-reload/cache-flush')]
final readonly class CacheFlushListener
{
    public function __construct(
        private ExtensionSettings $settings,
        private BroadcastTagCollector $collector,
    ) {
    }

    public function __invoke(CacheFlushEvent $event): void
    {
        if (!$this->settings->contextAllowed()) {
            return;
        }

        $this->collector->add(BroadcastTagCollector::RELOAD_ALL);
    }
}
