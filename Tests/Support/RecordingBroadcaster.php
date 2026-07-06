<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Support;

use Wazum\LiveReload\Broadcaster\TagBroadcasterInterface;

final class RecordingBroadcaster implements TagBroadcasterInterface
{
    /**
     * @var array<array<string>>
     */
    public array $received = [];

    public function broadcast(string ...$tags): void
    {
        $this->received[] = array_values($tags);
    }
}
