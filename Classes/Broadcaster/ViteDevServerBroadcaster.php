<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Broadcaster;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Http\RequestFactory;
use Wazum\ContentLiveReload\Configuration\ExtensionSettings;

final class ViteDevServerBroadcaster implements TagBroadcasterInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly RequestFactory $requestFactory,
    ) {
    }

    public function broadcast(string ...$tags): void
    {
        if ($tags === []) {
            return;
        }

        try {
            $this->requestFactory->request(
                $this->settings->viteServerInternalUrl() . '/__typo3-content-changed',
                'POST',
                [
                    'json' => ['tags' => array_values($tags)],
                    'timeout' => 0.5,
                    'connect_timeout' => 0.5,
                ],
            );
        } catch (Throwable $exception) {
            $this->logger?->debug('Content live reload broadcast failed', ['exception' => $exception]);
        }
    }
}
