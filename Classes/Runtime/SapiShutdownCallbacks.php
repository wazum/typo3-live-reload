<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Runtime;

final readonly class SapiShutdownCallbacks implements ShutdownCallbacks
{
    public function register(callable $callback): void
    {
        register_shutdown_function($callback);
    }
}
