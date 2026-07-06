<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Broadcaster;

interface TagBroadcasterInterface
{
    public function broadcast(string ...$tags): void;
}
