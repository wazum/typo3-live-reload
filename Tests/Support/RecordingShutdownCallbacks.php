<?php

declare(strict_types=1);

namespace Wazum\LiveReload\Tests\Support;

use Wazum\LiveReload\Runtime\ShutdownCallbacks;

final class RecordingShutdownCallbacks implements ShutdownCallbacks
{
    /**
     * @var array<callable>
     */
    private array $callbacks = [];

    public function register(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function runAndCaptureOutput(): string
    {
        ob_start();
        foreach ($this->callbacks as $callback) {
            $callback();
        }

        return (string)ob_get_clean();
    }
}
