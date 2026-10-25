<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Runtime;

interface ShutdownCallbacks
{
    public function register(callable $callback): void;
}
