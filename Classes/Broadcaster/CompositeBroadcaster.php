<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcaster;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use Wazum\LiveReload\Configuration\ExtensionSettings;

final class CompositeBroadcaster implements TagBroadcasterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly BroadcastLogWriter $broadcastLogWriter,
        private readonly ViteDevServerBroadcaster $viteDevServerBroadcaster,
    ) {
    }

    public function broadcast(string ...$tags): void
    {
        $this->deliver($this->broadcastLogWriter, ...$tags);
        if (!$this->settings->developmentContext()) {
            return;
        }

        $this->deliver($this->viteDevServerBroadcaster, ...$tags);
    }

    private function deliver(TagBroadcasterInterface $broadcaster, string ...$tags): void
    {
        try {
            $broadcaster->broadcast(...$tags);
        } catch (Throwable $exception) {
            $this->logger?->warning('Live reload broadcast transport failed', ['exception' => $exception]);
        }
    }
}
