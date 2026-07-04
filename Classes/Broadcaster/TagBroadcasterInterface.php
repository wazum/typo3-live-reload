<?php

declare(strict_types=1);

namespace Wazum\ContentLiveReload\Broadcaster;

interface TagBroadcasterInterface
{
    public function broadcast(string ...$tags): void;
}
