<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcaster;

use Wazum\LiveReload\Configuration\ExtensionSettings;

final class CompositeBroadcaster implements TagBroadcasterInterface
{
    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly BroadcastLogWriter $broadcastLogWriter,
        private readonly ViteDevServerBroadcaster $viteDevServerBroadcaster,
    ) {
    }

    public function broadcast(string ...$tags): void
    {
        $this->broadcastLogWriter->broadcast(...$tags);
        if (!$this->settings->developmentContext()) {
            return;
        }

        $this->viteDevServerBroadcaster->broadcast(...$tags);
    }
}
