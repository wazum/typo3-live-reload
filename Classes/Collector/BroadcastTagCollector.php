<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Collector;

use TYPO3\CMS\Core\SingletonInterface;
use Wazum\ContentLiveReload\Broadcaster\TagBroadcasterInterface;
use Wazum\ContentLiveReload\Runtime\ResponseDetacher;

final class BroadcastTagCollector implements SingletonInterface
{
    /**
     * @var array<string, true>
     */
    private array $tags = [];

    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly TagBroadcasterInterface $broadcaster,
        private readonly ResponseDetacher $detacher,
    ) {
    }

    public function add(string ...$tags): void
    {
        foreach ($tags as $tag) {
            $this->tags[$tag] = true;
        }

        if ($this->tags === [] || $this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;
        register_shutdown_function($this->flush(...));
    }

    public function flush(): void
    {
        if ($this->tags === []) {
            return;
        }

        $tags = array_keys($this->tags);
        $this->tags = [];
        $this->detacher->detach();
        $this->broadcaster->broadcast(...$tags);
    }
}
